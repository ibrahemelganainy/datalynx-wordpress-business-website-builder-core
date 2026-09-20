<?php

namespace BusinessBuilderCore\Core\Payments\Checkout;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentGatewayInterface;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Payment checkout orchestrator.
 *
 * Given a validated CheckoutRequest, this class:
 *   1. confirms the chosen gateway is enabled AND configured,
 *   2. creates a pending transaction and persists it (CPT store),
 *   3. asks the gateway to create the payment,
 *   4. returns a routing instruction the caller uses to move the
 *      customer to the provider (redirect) or to show manual
 *      instructions.
 *
 * Security: the amount/currency come from the request only as a hint;
 * business code is expected to build the request from server-side
 * values. The gateway (not the client) decides the final payable state.
 * Nothing here ever trusts a client-supplied "paid" flag.
 */
class PaymentCheckout {

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Constructor.
     *
     * @param PaymentManager $payments Payments.
     * @param AuditLog       $audit    Audit log.
     */
    public function __construct( PaymentManager $payments, AuditLog $audit ) {
        $this->payments = $payments;
        $this->audit    = $audit;
    }

    /**
     * Start a checkout for a request.
     *
     * @param CheckoutRequest $request Request.
     * @return array<string, mixed> Result: ['type'=>'redirect'|'manual'|'error', ...]
     */
    public function start( CheckoutRequest $request ): array {

        $valid = $request->is_valid();

        if ( false === $valid ) {
            return array(
                'type'    => 'error',
                'code'    => 'invalid_request',
                'message' => implode( ' ', $request->errors ),
            );
        }

        $gateway = $this->payments->gateway( $request->gateway );

        if ( ! $gateway instanceof PaymentGatewayInterface ) {
            return $this->error( 'gateway_unknown', __( 'The selected payment method is not available.', 'business-builder' ) );
        }

        /* The gateway must be enabled AND fully configured. */
        $enabled = $this->payments->is_gateway_enabled( $gateway->get_id() );

        if ( false === $enabled ) {
            return $this->error( 'gateway_disabled', __( 'The selected payment method is not enabled.', 'business-builder' ) );
        }

        $configured = $gateway->is_configured();

        if ( false === $configured ) {
            return $this->error( 'gateway_not_configured', __( 'The selected payment method is not configured yet.', 'business-builder' ) );
        }

        /* Validate the amount + currency before touching the gateway. */
        if ( '' === $request->amount || (float) $request->amount <= 0 ) {
            return $this->error( 'invalid_amount', __( 'A valid amount is required to continue.', 'business-builder' ) );
        }

        if ( '' === $request->currency ) {
            return $this->error( 'invalid_currency', __( 'A currency is required to continue.', 'business-builder' ) );
        }

        $transaction = $this->create_transaction( $request, $gateway );

        $result = $gateway->create_payment( $transaction );

        $type = isset( $result['type'] ) ? (string) $result['type'] : 'unavailable';

        $handled = $this->handle_result( $transaction, $gateway, $type, $result );

        /*
         * If the gateway could not start the payment, the transaction must
         * NOT be left in an orphaned "pending" state: record it as failed
         * (with the structured code + a sanitized provider reason) so the
         * administrator can see exactly why, and no phantom pending payment
         * accumulates in the dashboard.
         */
        if ( isset( $handled['type'] ) && 'error' === $handled['type'] && $transaction->id > 0 ) {

            $code = isset( $handled['code'] ) ? sanitize_key( (string) $handled['code'] ) : 'gateway_request_failed';
            $meta = is_array( $transaction->meta ) ? $transaction->meta : array();

            $meta['failure_code']   = $code;
            $meta['failure_reason'] = isset( $handled['message'] ) ? sanitize_text_field( (string) $handled['message'] ) : '';
            $meta['failed_at']      = current_time( 'mysql' );

            $failed = $this->payments->store()->find( $transaction->id );

            if ( $failed instanceof PaymentTransaction ) {
                $failed->meta   = $meta;
                $failed->status = 'failed';
                $failed         = $this->payments->persist( $failed );

                /* Surface the failed transaction to the caller too. */
                $handled['transaction'] = $failed;
            }

            $this->audit->record(
                'payment.checkout_failed',
                'payment',
                $transaction->id,
                array( 'gateway' => $gateway->get_id(), 'code' => $code )
            );
        }

        return $handled;
    }

    /**
     * Build and persist a pending transaction for the request.
     *
     * @param CheckoutRequest        $request Request.
     * @param PaymentGatewayInterface $gateway Gateway.
     * @return PaymentTransaction
     */
    protected function create_transaction( CheckoutRequest $request, PaymentGatewayInterface $gateway ): PaymentTransaction {

        $transaction = PaymentTransaction::from_array(
            array(
                'public_ref'      => Reference::transaction(),
                'object_type'     => $request->object_type,
                'object_id'       => $request->object_id,
                'consultation_id' => 'consultation' === $request->object_type ? $request->object_id : 0,
                'gateway'         => $gateway->get_id(),
                'amount'          => $request->amount,
                'currency'        => $request->currency,
                'status'          => 'pending',
                'meta'            => array_filter(
                    array(
                        'label'      => $request->label,
                        'email'      => $request->email,
                        'name'       => isset( $request->billing['name'] ) ? $request->billing['name'] : '',
                        'first_name' => isset( $request->billing['first_name'] ) ? $request->billing['first_name'] : '',
                        'last_name'  => isset( $request->billing['last_name'] ) ? $request->billing['last_name'] : '',
                        'phone'      => isset( $request->billing['phone'] ) ? $request->billing['phone'] : '',
                        'origin'     => isset( $request->billing['origin'] ) ? $request->billing['origin'] : '',
                        'created_by' => 'checkout',
                    )
                ),
            )
        );

        return $this->payments->persist( $transaction );
    }

    /**
     * Translate a gateway create_payment() result into a routing
     * instruction, persisting the provider reference when present.
     *
     * @param PaymentTransaction       $transaction Transaction.
     * @param PaymentGatewayInterface  $gateway     Gateway.
     * @param string                   $type        Result type.
     * @param array<string, mixed>     $result      Raw result.
     * @return array<string, mixed>
     */
    protected function handle_result(
        PaymentTransaction $transaction,
        PaymentGatewayInterface $gateway,
        string $type,
        array $result
    ): array {

        $message = isset( $result['message'] ) ? (string) $result['message'] : '';

        switch ( $type ) {

            case 'redirect':

                $url = isset( $result['url'] ) ? (string) $result['url'] : $gateway->get_payment_url( $transaction );

                if ( '' === $url ) {
                    return $this->error( 'redirect_missing', __( 'The payment provider could not be reached. Please try again.', 'business-builder' ) );
                }

                $has_reference = ! empty( $result['reference'] );

                if ( $has_reference ) {
                    $transaction = $this->payments->record_status_change(
                        $transaction->id,
                        'processing',
                        (string) $result['reference']
                    ) ?? $transaction;
                }

                $this->audit->record(
                    'payment.checkout_redirect',
                    'payment',
                    $transaction->id,
                    array( 'gateway' => $gateway->get_id() )
                );

                return array(
                    'type'        => 'redirect',
                    'url'         => $url,
                    'transaction' => $transaction,
                    'message'     => $message,
                );

            case 'reference':

                /*
                 * Reference-number gateways (e.g. Fawry): the customer pays
                 * offline/online with a provider reference. The transaction
                 * moves to "awaiting_payment" and is only settled by a
                 * verified callback/webhook — never by the browser.
                 */
                $reference = isset( $result['reference'] ) ? (string) $result['reference'] : '';

                if ( '' !== $reference ) {
                    $transaction = $this->payments->record_status_change(
                        $transaction->id,
                        'awaiting_payment',
                        $reference
                    ) ?? $transaction;
                }

                $this->audit->record(
                    'payment.checkout_reference',
                    'payment',
                    $transaction->id,
                    array( 'gateway' => $gateway->get_id() )
                );

                return array(
                    'type'        => 'reference',
                    'reference'   => $reference,
                    'transaction' => $transaction,
                    'message'     => '' !== $message
                        ? $message
                        : __( 'Pay using the reference number shown and keep it safe.', 'business-builder' ),
                );

            case 'manual':

                $this->audit->record(
                    'payment.checkout_manual',
                    'payment',
                    $transaction->id,
                    array( 'gateway' => $gateway->get_id() )
                );

                return array(
                    'type'        => 'manual',
                    'transaction' => $transaction,
                    'message'     => '' !== $message
                        ? $message
                        : __( 'Follow the offline instructions to complete your payment.', 'business-builder' ),
                );

            default:

                /*
                 * The gateway is not ready to take payments (e.g. an
                 * unimplemented API gateway). Record nothing as paid and
                 * surface the honest provider message.
                 */
                return $this->error(
                    'gateway_request_failed',
                    '' !== $message
                        ? $message
                        : __( 'This payment method is not available right now. Please choose another.', 'business-builder' ),
                    $transaction
                );
        }
    }

    /**
     * Build an error result.
     *
     * @param string                  $code        Structured error code.
     * @param string                  $message     Human message.
     * @param PaymentTransaction|null $transaction Transaction (optional).
     * @return array<string, mixed>
     */
    protected function error( string $code, string $message, ?PaymentTransaction $transaction = null ): array {

        return array(
            'type'        => 'error',
            'code'        => sanitize_key( $code ),
            'message'     => $message,
            'transaction' => $transaction,
        );
    }
}
