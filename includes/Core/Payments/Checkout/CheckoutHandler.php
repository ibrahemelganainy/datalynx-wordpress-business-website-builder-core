<?php

namespace BusinessBuilderCore\Core\Payments\Checkout;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend checkout endpoint.
 *
 * Mirrors the safe ConsultationForm / BookingForm pattern: posts to
 * admin-post.php (works for logged-out visitors), verifies a nonce,
 * sanitizes every field through CheckoutRequest, runs the checkout and
 * redirects back with a status flag. Raw input is never echoed back.
 *
 * On a successful redirect/mnaual checkout the customer is sent to the
 * status page so they can follow the payment with their public
 * reference (the reference is the only thing exposed).
 */
class CheckoutHandler {

    /**
     * Form action name.
     */
    public const ACTION = 'bb_checkout';

    /**
     * Nonce action.
     */
    public const NONCE_ACTION = 'bb_checkout_submit';

    /**
     * Nonce field name.
     */
    public const NONCE_FIELD = 'bb_checkout_nonce';

    /**
     * Checkout orchestrator.
     */
    protected PaymentCheckout $checkout;

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
     * @param PaymentCheckout $checkout Checkout.
     * @param PaymentManager  $payments Payments.
     * @param AuditLog        $audit    Audit log.
     */
    public function __construct( PaymentCheckout $checkout, PaymentManager $payments, AuditLog $audit ) {
        $this->checkout = $checkout;
        $this->payments = $payments;
        $this->audit    = $audit;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
        add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
    }

    /**
     * Handle a submitted checkout.
     */
    public function handle(): void {

        $redirect = $this->redirect_base();

        $nonce_field = self::NONCE_FIELD;

        $verify = $this->verify_nonce( $nonce_field );

        if ( false === $verify ) {
            $this->redirect_with( $redirect, 'error' );
        }

        $request = CheckoutRequest::from_array( $_POST );

        $result = $this->checkout->start( $request );

        $type = isset( $result['type'] ) ? (string) $result['type'] : 'error';

        if ( 'redirect' === $type ) {

            $url = isset( $result['url'] ) ? (string) $result['url'] : '';

            if ( '' !== $url ) {
                wp_redirect( $url );
                exit;
            }

            $this->redirect_with( $redirect, 'error' );
        }

        if ( 'manual' === $type ) {

            $transaction = $result['transaction'] instanceof PaymentTransaction ? $result['transaction'] : null;

            $this->redirect_with( $redirect, 'pending', $transaction );
        }

        $this->redirect_with( $redirect, 'error' );
    }

    /**
     * Verify the checkout nonce from POST.
     *
     * @param string $nonce_field Field name.
     * @return bool
     */
    protected function verify_nonce( string $nonce_field ): bool {

        $present = isset( $_POST[ $nonce_field ] );

        if ( false === $present ) {
            return false;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );

        return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
    }

    /**
     * Safe redirect base (referer host only, never a full attacker URL).
     *
     * @return string
     */
    protected function redirect_base(): string {

        $referer = wp_get_referer();

        return $referer ? $referer : home_url( '/' );
    }

    /**
     * Redirect back with a status flag; on pending, include the public
     * reference so the customer can follow up on the status page.
     *
     * @param string                  $url         Redirect base.
     * @param string                  $status      Status flag.
     * @param PaymentTransaction|null $transaction Transaction (optional).
     */
    protected function redirect_with( string $url, string $status, ?PaymentTransaction $transaction = null ): void {

        $url = add_query_arg(
            'bb_checkout',
            sanitize_key( $status ),
            remove_query_arg( 'bb_checkout', $url )
        );

        if ( $transaction instanceof PaymentTransaction && '' !== $transaction->public_ref ) {
            $url = add_query_arg( 'bb_ref', $transaction->public_ref, $url );
        }

        wp_safe_redirect( $url );
        exit;
    }

    /* ---- Static accessors for templates ---- */

    /**
     * Form action URL.
     *
     * @return string
     */
    public static function action_url(): string {

        return admin_url( 'admin-post.php' );
    }

    /**
     * Action name.
     *
     * @return string
     */
    public static function action_name(): string {

        return self::ACTION;
    }

    /**
     * Nonce field name.
     *
     * @return string
     */
    public static function nonce_field(): string {

        return self::NONCE_FIELD;
    }

    /**
     * Nonce action.
     *
     * @return string
     */
    public static function nonce_action(): string {

        return self::NONCE_ACTION;
    }
}
