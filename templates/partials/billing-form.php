<?php
/**
 * Dynamic billing-details partial (Paymob / providers that need billing_data).
 *
 * Included by templates/consultation-form.php and templates/booking-form.php
 * inside their payment block. It renders the four fields Paymob requires
 * (first name, last name, email, phone) and is:
 *
 *   - hidden by default (the `hidden` attribute + aria-hidden),
 *   - revealed by assets/js/frontend/payment-billing.js ONLY when the
 *     selected gateway is Paymob,
 *   - validated client-side before submit, and
 *   - re-validated server-side (never trusted from the browser).
 *
 * The inputs carry data-bb-billing-field attributes so the script can manage
 * `required`/`aria-invalid` generically without hard-coding ids.
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Set by the including template; keeps ids unique across multiple forms. */
$bb_billing_uid = isset( $bb_billing_uid ) ? sanitize_key( (string) $bb_billing_uid ) : 'consultation';

$bb_id = static function ( string $field ) use ( $bb_billing_uid ): string {
    return 'bb_billing_' . $bb_billing_uid . '_' . $field;
};

?>
<div
    class="bb-billing"
    data-bb-billing
    data-bb-billing-gateway="paymob"
    hidden
    aria-hidden="true"
>
    <h4 class="bb-billing-title">
        <?php esc_html_e( 'Billing Details', 'business-builder' ); ?>
    </h4>

    <p class="bb-billing-hint">
        <?php esc_html_e( 'These details are required by Paymob to process your card payment securely.', 'business-builder' ); ?>
    </p>

    <div class="bb-billing-grid">

        <div class="bb-billing-field">
            <label for="<?php echo esc_attr( $bb_id( 'first_name' ) ); ?>">
                <?php esc_html_e( 'First Name', 'business-builder' ); ?>
                <span class="bb-required" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr( $bb_id( 'first_name' ) ); ?>"
                name="bb_billing_first_name"
                class="bb-billing-input"
                data-bb-billing-field="first_name"
                autocomplete="given-name"
                placeholder="<?php esc_attr_e( 'e.g. Amina', 'business-builder' ); ?>"
                aria-describedby="<?php echo esc_attr( $bb_id( 'first_name' ) . '_error' ); ?>"
            />
            <span class="bb-billing-error" id="<?php echo esc_attr( $bb_id( 'first_name' ) . '_error' ); ?>" data-bb-billing-error="first_name" role="alert" aria-live="polite"></span>
        </div>

        <div class="bb-billing-field">
            <label for="<?php echo esc_attr( $bb_id( 'last_name' ) ); ?>">
                <?php esc_html_e( 'Last Name', 'business-builder' ); ?>
                <span class="bb-required" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr( $bb_id( 'last_name' ) ); ?>"
                name="bb_billing_last_name"
                class="bb-billing-input"
                data-bb-billing-field="last_name"
                autocomplete="family-name"
                placeholder="<?php esc_attr_e( 'e.g. Youssef', 'business-builder' ); ?>"
                aria-describedby="<?php echo esc_attr( $bb_id( 'last_name' ) . '_error' ); ?>"
            />
            <span class="bb-billing-error" id="<?php echo esc_attr( $bb_id( 'last_name' ) . '_error' ); ?>" data-bb-billing-error="last_name" role="alert" aria-live="polite"></span>
        </div>

        <div class="bb-billing-field">
            <label for="<?php echo esc_attr( $bb_id( 'email' ) ); ?>">
                <?php esc_html_e( 'Email', 'business-builder' ); ?>
                <span class="bb-required" aria-hidden="true">*</span>
            </label>
            <input
                type="email"
                id="<?php echo esc_attr( $bb_id( 'email' ) ); ?>"
                name="bb_billing_email"
                class="bb-billing-input"
                data-bb-billing-field="email"
                autocomplete="email"
                inputmode="email"
                placeholder="<?php esc_attr_e( 'you@example.com', 'business-builder' ); ?>"
                aria-describedby="<?php echo esc_attr( $bb_id( 'email' ) . '_error' ); ?>"
            />
            <span class="bb-billing-error" id="<?php echo esc_attr( $bb_id( 'email' ) . '_error' ); ?>" data-bb-billing-error="email" role="alert" aria-live="polite"></span>
        </div>

        <div class="bb-billing-field">
            <label for="<?php echo esc_attr( $bb_id( 'phone' ) ); ?>">
                <?php esc_html_e( 'Phone Number', 'business-builder' ); ?>
                <span class="bb-required" aria-hidden="true">*</span>
            </label>
            <input
                type="tel"
                id="<?php echo esc_attr( $bb_id( 'phone' ) ); ?>"
                name="bb_billing_phone"
                class="bb-billing-input"
                data-bb-billing-field="phone"
                autocomplete="tel"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="<?php esc_attr_e( 'e.g. 01012345678', 'business-builder' ); ?>"
                aria-describedby="<?php echo esc_attr( $bb_id( 'phone' ) . '_error' ); ?>"
            />
            <span class="bb-billing-error" id="<?php echo esc_attr( $bb_id( 'phone' ) . '_error' ); ?>" data-bb-billing-error="phone" role="alert" aria-live="polite"></span>
        </div>

    </div>
</div>
