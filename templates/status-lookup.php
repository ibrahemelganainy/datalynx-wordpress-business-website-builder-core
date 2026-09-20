<?php
/**
 * Consultation & Appointment Lookup section template (LawFirm pack).
 *
 * Rendered by LawFirmSections::render_lookup_section(). The customer must
 * provide BOTH a reference number and a phone number; the backend verifies
 * both against the same record before returning anything.
 *
 * Expected vars:
 *   $settings  Section settings (show_consultation/show_appointment/...).
 *   $bb_lookup_nonce Nonce for the AJAX request.
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Packs\LawFirm\Frontend\LookupHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bb_lookup_settings = isset( $settings ) && is_array( $settings ) ? $settings : array();

$bb_show_consult     = ! isset( $bb_lookup_settings['show_consultation'] ) || ! empty( $bb_lookup_settings['show_consultation'] );
$bb_show_appt        = ! isset( $bb_lookup_settings['show_appointment'] ) || ! empty( $bb_lookup_settings['show_appointment'] );
$bb_show_payment     = ! isset( $bb_lookup_settings['show_payment_details'] ) || ! empty( $bb_lookup_settings['show_payment_details'] );
$bb_show_receipt     = ! isset( $bb_lookup_settings['show_receipt_button'] ) || ! empty( $bb_lookup_settings['show_receipt_button'] );

$bb_lookup_nonce = LookupHandler::nonce();

/* If only one mode is enabled, preselect it. */
$bb_default_type = 'consultation';

if ( ! $bb_show_consult && $bb_show_appt ) {
    $bb_default_type = 'appointment';
}

?>
<div
    class="bb-lookup"
    id="bb-status-lookup"
    data-bb-lookup
    data-bb-lookup-action="<?php echo esc_attr( LookupHandler::ACTION ); ?>"
    data-bb-lookup-nonce="<?php echo esc_attr( $bb_lookup_nonce ); ?>"
    data-bb-show-payment="<?php echo $bb_show_payment ? '1' : '0'; ?>"
    data-bb-show-receipt="<?php echo $bb_show_receipt ? '1' : '0'; ?>"
>
    <form class="bb-lookup-form" data-bb-lookup-form novalidate>

        <fieldset class="bb-lookup-types">
            <legend class="bb-lookup-legend"><?php esc_html_e( 'Look up', 'business-builder' ); ?></legend>

            <div class="bb-lookup-tabs" role="radiogroup">
                <?php if ( $bb_show_consult ) : ?>
                    <label class="bb-lookup-tab">
                        <input
                            type="radio"
                            name="bb_lookup_type"
                            value="consultation"
                            <?php checked( 'consultation' === $bb_default_type ); ?>
                        />
                        <span><?php esc_html_e( 'Consultation', 'business-builder' ); ?></span>
                    </label>
                <?php endif; ?>

                <?php if ( $bb_show_appt ) : ?>
                    <label class="bb-lookup-tab">
                        <input
                            type="radio"
                            name="bb_lookup_type"
                            value="appointment"
                            <?php checked( 'appointment' === $bb_default_type ); ?>
                        />
                        <span><?php esc_html_e( 'Appointment', 'business-builder' ); ?></span>
                    </label>
                <?php endif; ?>
            </div>
        </fieldset>

        <div class="bb-lookup-fields">

            <div class="bb-lookup-field">
                <label for="bb_lookup_reference"><?php esc_html_e( 'Reference Number', 'business-builder' ); ?> <span class="bb-required">*</span></label>
                <input
                    type="text"
                    id="bb_lookup_reference"
                    name="reference"
                    data-bb-lookup-reference
                    autocomplete="off"
                    placeholder="CNS-XXXXXXXXXX"
                />
                <span class="bb-lookup-error" data-bb-lookup-error="reference" role="alert" aria-live="polite"></span>
            </div>

            <div class="bb-lookup-field">
                <label for="bb_lookup_phone"><?php esc_html_e( 'Phone Number', 'business-builder' ); ?> <span class="bb-required">*</span></label>
                <input
                    type="tel"
                    id="bb_lookup_phone"
                    name="phone"
                    data-bb-lookup-phone
                    autocomplete="tel"
                    inputmode="tel"
                />
                <span class="bb-lookup-error" data-bb-lookup-error="phone" role="alert" aria-live="polite"></span>
            </div>

        </div>

        <p class="bb-lookup-submit">
            <button type="submit" class="bb-primary-button bb-lookup-button">
                <span class="bb-lookup-spinner" aria-hidden="true"></span>
                <span class="bb-lookup-button-text"><?php esc_html_e( 'Check Status', 'business-builder' ); ?></span>
            </button>
        </p>

        <p class="bb-lookup-notice" data-bb-lookup-notice role="alert" aria-live="polite" hidden></p>
    </form>

    <div class="bb-lookup-result" data-bb-lookup-result hidden></div>
</div>
