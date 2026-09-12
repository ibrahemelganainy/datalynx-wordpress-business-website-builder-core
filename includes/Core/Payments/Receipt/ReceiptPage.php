<?php

namespace BusinessBuilderCore\Core\Payments\Receipt;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentGatewayInterface;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend payment receipt shortcode.
 *
 * Usage:
 *   [bb_payment_receipt reference="TXN-XXXXXXXXXX"]
 *   or visit a page with ?bb_ref=TXN-XXXXXXXXXX
 *
 * Security model:
 *   - requires a PUBLIC reference (never an internal id),
 *   - the reference is validated for shape before any lookup,
 *   - lookups are rate-limited per visitor to blunt enumeration,
 *   - the receipt is only rendered for a PAID transaction; anything
 *     else returns a single, uniform "not found/available" message,
 *   - nothing sensitive (gateway settings, secrets) is ever emitted.
 */
class ReceiptPage {

    /**
     * Shortcode tag.
     */
    public const SHORTCODE = 'bb_payment_receipt';

    /**
     * Query arg used as a reference fallback.
     */
    public const QUERY_ARG = 'bb_ref';

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Renderer.
     */
    protected ReceiptRenderer $renderer;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Constructor.
     *
     * @param PaymentManager  $payments Payments.
     * @param ReceiptRenderer $renderer Renderer.
     * @param AuditLog        $audit    Audit log.
     */
    public function __construct( PaymentManager $payments, ReceiptRenderer $renderer, AuditLog $audit ) {
        $this->payments = $payments;
        $this->renderer = $renderer;
        $this->audit    = $audit;
    }

    /**
     * Register the shortcode.
     */
    public function register(): void {

        add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode( $atts = array() ): string {

        $atts = shortcode_atts(
            array( 'reference' => '' ),
            is_array( $atts ) ? $atts : array(),
            self::SHORTCODE
        );

        $reference = (string) $atts['reference'];

        if ( '' === $reference ) {
            $reference = $this->reference_from_query();
        }

        return $this->render_for_reference( $reference );
    }

    /**
     * Read a reference from a query argument (sanitized).
     *
     * @return string
     */
    protected function reference_from_query(): string {

        $key = self::QUERY_ARG;

        $present = isset( $_GET[ $key ] );

        if ( false === $present ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
    }

    /**
     * Render a receipt for a reference (or a uniform notice).
     *
     * @param string $reference Candidate reference.
     * @return string
     */
    public function render_for_reference( string $reference ): string {

        $reference = Reference::normalize( $reference );

        $valid_shape = Reference::is_valid( $reference );

        if ( false === $valid_shape ) {
            return $this->notice(
                __( 'Enter a valid reference number to view your receipt.', 'business-builder' )
            );
        }

        if ( $this->is_rate_limited() ) {
            return $this->notice(
                __( 'Too many attempts. Please wait a moment and try again.', 'business-builder' )
            );
        }

        $this->bump_rate_limit();

        $transaction = $this->find( $reference );

        if ( ! $transaction instanceof PaymentTransaction ) {
            return $this->not_found();
        }

        if ( 'paid' !== $transaction->status ) {
            /*
             * A receipt exists only for settled payments. Do not reveal
             * whether the reference exists for a non-paid transaction.
             */
            return $this->not_found();
        }

        $gateway_name = $this->gateway_name( $transaction->gateway );

        $receipt = Receipt::from_transaction( $transaction, $gateway_name );

        $this->audit->record(
            'payment.receipt_viewed',
            'payment',
            $transaction->id,
            array( 'gateway' => $transaction->gateway )
        );

        return $this->renderer->render( $receipt );
    }

    /**
     * Find a transaction by public reference across both stores.
     *
     * @param string $reference Public reference.
     * @return PaymentTransaction|null
     */
    protected function find( string $reference ): ?PaymentTransaction {

        $transaction = $this->payments->store()->find_by_public_ref( $reference );

        if ( $transaction instanceof PaymentTransaction ) {
            return $transaction;
        }

        return $this->payments->find_by_public_ref( $reference );
    }

    /**
     * Human gateway name for a slug (never its settings).
     *
     * @param string $gateway_id Gateway id.
     * @return string
     */
    protected function gateway_name( string $gateway_id ): string {

        $gateway = $this->payments->gateway( $gateway_id );

        if ( $gateway instanceof PaymentGatewayInterface ) {
            return $gateway->get_name();
        }

        return $gateway_id;
    }

    /**
     * Uniform "not found" message (never reveals existence).
     *
     * @return string
     */
    protected function not_found(): string {

        return $this->notice(
            __( 'No receipt was found for that reference number.', 'business-builder' )
        );
    }

    /**
     * Wrap a message in safe markup.
     *
     * @param string $message Message.
     * @return string
     */
    protected function notice( string $message ): string {

        return '<div class="bb-receipt-notice">' . esc_html( $message ) . '</div>';
    }

    /**
     * Rate-limit key for the current visitor.
     *
     * @return string
     */
    protected function rate_key(): string {

        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : 'unknown';

        return 'bb_receipt_rl_' . md5( $ip . '|' . get_current_blog_id() );
    }

    /**
     * Whether the visitor has exceeded the lookup budget.
     *
     * @return bool
     */
    protected function is_rate_limited(): bool {

        $count = (int) get_transient( $this->rate_key() );

        /** Filter the per-window receipt lookup budget. */
        $max = (int) apply_filters( 'bb_receipt_rate_limit', 20 );

        return $count >= max( 1, $max );
    }

    /**
     * Increment the visitor's lookup counter.
     */
    protected function bump_rate_limit(): void {

        $key   = $this->rate_key();
        $count = (int) get_transient( $key );

        set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );
    }
}
