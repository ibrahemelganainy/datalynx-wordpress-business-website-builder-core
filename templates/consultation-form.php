<?php
/**
 * Consultation form template (LawFirm pack).
 *
 * Rendered by LawFirmSections::render_consultation_section().
 * Posts to admin-post.php so it works for logged-out visitors.
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Packs\LawFirm\Frontend\ConsultationForm;
use BusinessBuilderCore\Core\Payments\Currencies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bb_areas = ConsultationForm::practice_areas();

/*
 * Section payment configuration resolved by the render callback. When
 * payment is payable, the customer must choose a gateway and pay before
 * the request is confirmed; the fee/currency/gateways are always the
 * server-resolved values, never client input.
 */
$bb_pay        = isset( $bb_payment ) && is_array( $bb_payment ) ? $bb_payment : array();
$bb_payable    = ! empty( $bb_pay['payable'] );
$bb_fee        = isset( $bb_pay['fee'] ) ? (string) $bb_pay['fee'] : '';
$bb_currency   = isset( $bb_pay['currency'] ) ? (string) $bb_pay['currency'] : '';
$bb_gateways   = isset( $bb_pay['available'] ) && is_array( $bb_pay['available'] ) ? $bb_pay['available'] : array();
$bb_section_id = isset( $bb_section_id ) ? (string) $bb_section_id : '';
$bb_page_id    = isset( $bb_page_id ) ? (int) $bb_page_id : 0;

$bb_status = isset( $_GET['bb_consult'] )
    ? sanitize_key( wp_unslash( $_GET['bb_consult'] ) )
    : '';


?>

<div class="bb-consultation" id="bb-consultation-form">

    <?php if ( 'success' === $bb_status ) : ?>
        <div class="bb-consultation-notice bb-consultation-success">
            <?php esc_html_e( 'Thank you. Your consultation request has been received and our team will contact you shortly.', 'business-builder' ); ?>
        </div>
    <?php elseif ( 'pending' === $bb_status ) : ?>
        <div class="bb-consultation-notice bb-consultation-success">
            <?php esc_html_e( 'Your request was received. Please complete the payment below to confirm your consultation.', 'business-builder' ); ?>
        </div>
    <?php elseif ( 'payment_error' === $bb_status ) : ?>
        <div class="bb-consultation-notice bb-consultation-error">
            <?php esc_html_e( 'Your request was received, but the payment could not be started. Please choose a payment method and try again.', 'business-builder' ); ?>
        </div>
    <?php elseif ( 'error' === $bb_status ) : ?>
        <div class="bb-consultation-notice bb-consultation-error">
            <?php esc_html_e( 'Sorry, your request could not be sent. Please check the required fields and try again.', 'business-builder' ); ?>
        </div>
    <?php endif; ?>

    <form
        class="bb-consultation-form"
        method="post"
        action="<?php echo esc_url( ConsultationForm::action_url() ); ?>"
    >

        <input
            type="hidden"
            name="action"
            value="<?php echo esc_attr( ConsultationForm::action_name() ); ?>"
        />

        <input type="hidden" name="bb_page_id" value="<?php echo esc_attr( (string) $bb_page_id ); ?>" />
        <input type="hidden" name="bb_section_id" value="<?php echo esc_attr( $bb_section_id ); ?>" />

        <?php
        wp_nonce_field(
            ConsultationForm::nonce_action(),
            ConsultationForm::nonce_field()
        );
        ?>

        <div class="bb-consultation-grid">

            <p class="bb-consultation-field">
                <label for="bb_name"><?php esc_html_e( 'Name', 'business-builder' ); ?> <span class="bb-required">*</span></label>
                <input type="text" name="bb_name" id="bb_name" required />
            </p>

            <p class="bb-consultation-field">
                <label for="bb_phone"><?php esc_html_e( 'Phone', 'business-builder' ); ?></label>
                <input type="tel" name="bb_phone" id="bb_phone" />
            </p>

            <p class="bb-consultation-field">
                <label for="bb_email"><?php esc_html_e( 'Email', 'business-builder' ); ?></label>
                <input type="email" name="bb_email" id="bb_email" />
            </p>

            <p class="bb-consultation-field">
                <label for="bb_practice_area"><?php esc_html_e( 'Practice Area', 'business-builder' ); ?></label>
                <select name="bb_practice_area" id="bb_practice_area">
                    <option value=""><?php esc_html_e( 'Select a practice area', 'business-builder' ); ?></option>
                    <?php foreach ( $bb_areas as $bb_area ) : ?>
                        <option value="<?php echo esc_attr( (string) $bb_area->term_id ); ?>">
                            <?php echo esc_html( $bb_area->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="bb-consultation-field">
                <label for="bb_preferred_contact"><?php esc_html_e( 'Preferred Contact Method', 'business-builder' ); ?></label>
                <select name="bb_preferred_contact" id="bb_preferred_contact">
                    <option value="phone"><?php esc_html_e( 'Phone', 'business-builder' ); ?></option>
                    <option value="email"><?php esc_html_e( 'Email', 'business-builder' ); ?></option>
                    <option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'business-builder' ); ?></option>
                </select>
            </p>

        </div>

        <p class="bb-consultation-field">
            <label for="bb_message"><?php esc_html_e( 'Message', 'business-builder' ); ?> <span class="bb-required">*</span></label>
            <textarea name="bb_message" id="bb_message" rows="5" required></textarea>
        </p>

        <?php if ( $bb_payable ) : ?>

            <?php
            /*
             * Payment block. Shown only when this section requires payment
             * AND at least one enabled + configured gateway is available for
             * it. The fee/currency are the server-resolved values; the
             * gateway set is limited to the ones allowed for this section.
             */
            ?>
            <div class="bb-consultation-payment" id="bb-consultation-payment">

                <h3 class="bb-consultation-payment-title">
                    <?php esc_html_e( 'Consultation Fee', 'business-builder' ); ?>
                </h3>

                <p class="bb-consultation-payment-amount">
                    <?php echo esc_html( Currencies::format( (float) $bb_fee, $bb_currency, true ) ); ?>
                </p>

                <p class="bb-consultation-payment-note">
                    <?php esc_html_e( 'Payment is required to confirm this consultation.', 'business-builder' ); ?>
                </p>

                <?php if ( ! empty( $bb_gateways )) : ?>
                    <fieldset class="bb-payment-methods" role="radiogroup">
                        <legend class="bb-payment-legend">
                            <?php esc_html_e( 'Payment Method', 'business-builder' ); ?>
                        </legend>

                        <div class="bb-payment-grid">
                            <?php $bb_first = true; ?>
                            <?php foreach ( $bb_gateways as $bb_gw_id => $bb_gw ) : ?>
                                <?php $bb_logo = $bb_gw->get_logo_url(); ?>
                                <label class="bb-payment-option" data-gateway="<?php echo esc_attr( (string) $bb_gw_id ); ?>">
                                    <input
                                        class="bb-payment-radio"
                                        type="radio"
                                        name="bb_payment_gateway"
                                        value="<?php echo esc_attr( (string) $bb_gw_id ); ?>"
                                        <?php checked( $bb_first ); ?>
                                    />
                                    <span class="bb-payment-option-body">
                                        <?php if ( '' !== $bb_logo ) : ?>
                                            <span class="bb-payment-logo">
                                                <img src="<?php echo esc_url( $bb_logo ); ?>" alt="<?php echo esc_attr( $bb_gw->get_name() ); ?>" loading="lazy" width="38" height="38" />
                                            </span>
                                        <?php endif; ?>
                                        <span class="bb-payment-option-text">
                                            <span class="bb-payment-option-name"><?php echo esc_html( $bb_gw->get_name() ); ?></span>
                                            <?php if ( '' !== $bb_gw->get_description() ) : ?>
                                                <span class="bb-payment-option-desc"><?php echo esc_html( $bb_gw->get_description() ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="bb-payment-check" aria-hidden="true"></span>
                                    </span>
                                </label>
                                <?php $bb_first = false; ?>
                            <?php endforeach; ?>
                        </div>

                        <p class="bb-payment-secure">
                            <span class="bb-payment-secure-icon" aria-hidden="true">🔒</span>
                            <?php esc_html_e( 'Your payment is processed securely by the provider. We never store card details.', 'business-builder' ); ?>
                        </p>
                    </fieldset>
                <?php elseif ( ! empty( $bb_pay['enabled'] )) : ?>
                    <?php
                    /*
                     * Payment is required by this section but no gateway is
                     * enabled+configured. Show an explicit configuration
                     * notice instead of failing silently or offering nothing.
                     */
                    ?>
                    <div class="bb-payment-unconfigured" role="alert">
                        <?php esc_html_e( 'Payment is required for this consultation, but no payment method is available yet. Please contact us to complete your request.', 'business-builder' ); ?>
                    </div>
                <?php endif; ?>

            </div>

        <?php endif; ?>

        <p class="bb-consultation-submit">
            <button type="submit" class="bb-primary-button">
                <?php
                if ( $bb_payable ) {
                    esc_html_e( 'Continue to Payment', 'business-builder' );
                } else {
                    esc_html_e( 'Request a Consultation', 'business-builder' );
                }
                ?>
            </button>
        </p>

    </form>

</div>
