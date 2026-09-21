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

$bb_status = isset( $_GET['bb_consult'] )
    ? sanitize_key( wp_unslash( $_GET['bb_consult'] ) )
    : '';

/*
 * Payment context injected by LawFirmSections::render_consultation_section().
 * When absent (e.g. the template is included elsewhere), payment is off.
 */
$bb_pay        = isset( $bb_payment ) && is_array( $bb_payment ) ? $bb_payment : array( 'enabled' => false, 'payable' => false, 'fee' => '', 'currency' => '', 'available' => array() );
$bb_payable    = ! empty( $bb_pay['payable'] );
$bb_fee        = isset( $bb_pay['fee'] ) ? (string) $bb_pay['fee'] : '';
$bb_currency   = isset( $bb_pay['currency'] ) ? (string) $bb_pay['currency'] : '';
$bb_gateways   = isset( $bb_pay['available'] ) && is_array( $bb_pay['available'] ) ? $bb_pay['available'] : array();
$bb_section_id = isset( $bb_section_id ) ? (string) $bb_section_id : '';
$bb_page_id    = isset( $bb_page_id ) ? (int) $bb_page_id : 0;

?>
<div class="bb-consultation" id="bb-consultation-form">

    <?php
    /*
     * Payment state flags. The gateway callback redirects back with
     * bb_checkout=paid|pending and (when known) the public bb_ref, so the
     * SAME page can show the success state AND the receipt on screen — with
     * no navigation to a generic ?bb_ref= page.
     */
    $bb_checkout_state = isset( $_GET['bb_checkout'] ) ? sanitize_key( wp_unslash( $_GET['bb_checkout'] )) : '';
    $bb_raw_ref        = isset( $_GET['bb_ref'] ) ? wp_unslash( $_GET['bb_ref'] ) : '';
    $bb_receipt_ref    = sanitize_text_field( (string) $bb_raw_ref );

    if ( 'payment_error' === $bb_status ) {
        $bb_state = 'payment_error';
    } elseif ( 'pending' === $bb_status || 'pending' === $bb_checkout_state ) {
        $bb_state = 'pending';
    } elseif ( 'paid' === $bb_checkout_state ) {
        $bb_state = 'paid';
    } else {
        $bb_state = $bb_status;
    }
    ?>

    <?php if ( 'paid' === $bb_state ) : ?>
        <div class="bb-consultation-notice bb-consultation-success">
            <p><?php esc_html_e( 'Payment received. Your consultation is confirmed — your receipt is shown below.', 'business-builder' ); ?></p>
            <?php if ( '' !== $bb_receipt_ref ) : ?>
                <button type="button" class="bb-primary-button bb-notice-receipt-link" data-bb-receipt-open data-bb-receipt-auto data-bb-receipt-ref="<?php echo esc_attr( $bb_receipt_ref ); ?>">
                    <?php esc_html_e( 'View Receipt', 'business-builder' ); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php elseif ( 'pending' === $bb_state ) : ?>
        <div class="bb-consultation-notice bb-consultation-success">
            <p><?php esc_html_e( 'Your request was received and is awaiting payment verification. You will receive a confirmation once an administrator verifies your payment.', 'business-builder' ); ?></p>
            <?php if ( '' !== $bb_receipt_ref ) : ?>
                <button type="button" class="bb-primary-button bb-notice-receipt-link" data-bb-receipt-open data-bb-receipt-auto data-bb-receipt-ref="<?php echo esc_attr( $bb_receipt_ref ); ?>">
                    <?php esc_html_e( 'View Receipt', 'business-builder' ); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php elseif ( 'success' === $bb_state ) : ?>
        <div class="bb-consultation-notice bb-consultation-success">
            <?php esc_html_e( 'Thank you. Your consultation request has been received and our team will contact you shortly.', 'business-builder' ); ?>
        </div>
    <?php elseif ( 'payment_error' === $bb_state ) : ?>
        <?php
        /*
         * Show the REAL reason (from the structured checkout result) plus a
         * clear next step — never a single opaque message.
         */
        $bb_pay_reason = isset( $_GET['bb_pay_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['bb_pay_reason'] )) : '';
        $bb_pay_code   = isset( $_GET['bb_pay_code'] ) ? sanitize_key( wp_unslash( $_GET['bb_pay_code'] )) : '';
        ?>
        <div class="bb-consultation-notice bb-consultation-error">
            <p><strong><?php esc_html_e( 'The payment could not be started.', 'business-builder' ); ?></strong></p>
            <?php if ( '' !== $bb_pay_reason ) : ?>
                <p><?php echo esc_html( $bb_pay_reason ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Please choose another payment method, or contact us so we can help.', 'business-builder' ); ?></p>
            <?php endif; ?>
            <?php if ( '' !== $bb_pay_code ) : ?>
                <p class="bb-consultation-error-code"><code><?php echo esc_html( $bb_pay_code ); ?></code></p>
            <?php endif; ?>
        </div>
    <?php elseif ( 'error' === $bb_state ) : ?>
        <div class="bb-consultation-notice bb-consultation-error">
            <?php esc_html_e( 'Sorry, your request could not be sent. Please check the required fields and try again.', 'business-builder' ); ?>
        </div>
    <?php endif; ?>

    <form
        class="bb-consultation-form"
        method="post"
        action="<?php echo esc_url( ConsultationForm::action_url() ); ?>"
        enctype="multipart/form-data"
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
                                        data-bb-manual-toggle
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

                        <?php
                        /*
                         * Per-gateway manual instructions. Rendered for every
                         * manual gateway and revealed by JS only while its
                         * option is selected, so the customer sees the REAL
                         * configured wallet / InstaPay / bank details plus the
                         * transaction-reference and receipt-upload fields.
                         */
                        $bb_manual_tpl = BB_CORE_PATH . 'templates/partials/manual-payment-instructions.php';

                        foreach ( $bb_gateways as $bb_gw_id => $bb_gw ) {

                            if ( ! $bb_gw->is_manual() ) {
                                continue;
                            }

                            $bb_manual_gateway  = $bb_gw;
                            $bb_manual_gw_id    = (string) $bb_gw_id;
                            $bb_manual_amount   = $bb_fee;
                            $bb_manual_currency = $bb_currency;
                            $bb_manual_uid      = 'consultation-' . sanitize_key( (string) $bb_gw_id );

                            include $bb_manual_tpl;
                        }
                        ?>

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
