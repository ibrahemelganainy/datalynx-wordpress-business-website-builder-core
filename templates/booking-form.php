<?php
/**
 * Appointment booking form template (LawFirm pack).
 *
 * Rendered by LawFirmSections::render_booking_section(). Posts to
 * admin-post.php so it works for logged-out visitors. Availability is
 * enforced server-side by the Availability service; the slot list here is
 * a convenience only.
 *
 * Billing details (Paymob) are NOT collected here — they are collected on
 * the dedicated /paymob-billing/ page, which the handler redirects to when
 * a billing gateway is selected.
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Packs\LawFirm\Appointments\BookingForm;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Core\Payments\Currencies;

if ( ! defined( 'ABSPATH' ))  {
    exit;
}

$bb_lawyers = get_posts(
    array(
        'post_type'      => 'bb_lawyer',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'orderby'        => 'title',
        'order'          => 'ASC',
    )
);

$bb_status = isset( $_GET['bb_booking'] )
    ? sanitize_key( wp_unslash( $_GET['bb_booking'] ) )
    : '';

$bb_status_messages = array(
    'success'       => __( 'Your appointment request has been received. We will confirm it shortly.', 'business-builder' ),
    'taken'         => __( 'Sorry, that time slot was just taken. Please choose another slot.', 'business-builder' ),
    'invalid_time'  => __( 'Please choose a valid time for your appointment.', 'business-builder' ),
    'invalid_date'  => __( 'Please choose a valid date for your appointment.', 'business-builder' ),
    'past_date'     => __( 'Please choose a date in the future.', 'business-builder' ),
    'outside_hours' => __( 'That time is outside our booking hours. Please choose another time.', 'business-builder' ),
    'closed'        => __( 'We are not taking bookings on the selected day. Please choose another day.', 'business-builder' ),
    'pending'       => __( 'Your appointment was held and is awaiting payment verification. You will receive a confirmation once an administrator verifies your payment.', 'business-builder' ),
    'payment_error' => __( 'Your appointment was held, but the payment could not be started. Please choose a payment method and try again.', 'business-builder' ),
    'error'         => __( 'Sorry, your request could not be submitted. Please check the required fields.', 'business-builder' ),
);

/*
 * Section payment configuration resolved by the render callback. When
 * payable, the customer must choose a gateway and pay before the booking
 * is confirmed; the fee/currency/gateways are the server-resolved values.
 */
$bb_pay        = isset( $bb_payment ) && is_array( $bb_payment ) ? $bb_payment : array();
$bb_payable    = ! empty( $bb_pay['payable'] );
$bb_fee        = isset( $bb_pay['fee'] ) ? (string) $bb_pay['fee'] : '';
$bb_currency   = isset( $bb_pay['currency'] ) ? (string) $bb_pay['currency'] : '';
$bb_gateways   = isset( $bb_pay['available'] ) && is_array( $bb_pay['available'] ) ? $bb_pay['available'] : array();
$bb_section_id = isset( $bb_section_id ) ? (string) $bb_section_id : '';
$bb_page_id    = isset( $bb_page_id ) ? (int) $bb_page_id : 0;

$bb_area_terms = get_terms(
    array(
        'taxonomy'   => 'bb_practice_area',
        'hide_empty' => false,
    )
);

if ( is_wp_error( $bb_area_terms ) || ! is_array( $bb_area_terms ))  {
    $bb_area_terms = array();
}

?>

<div class="bb-booking" id="bb-booking-form">

    <?php
    /*
     * Payment state flags (see the consultation template). The callback
     * redirects back with bb_checkout=paid|pending (+ the public bb_ref), so
     * the SAME page shows the success state and the receipt on screen.
     */
    $bb_checkout_state = isset( $_GET['bb_checkout'] ) ? sanitize_key( wp_unslash( $_GET['bb_checkout'] )) : '';
    $bb_raw_apt_ref    = isset( $_GET['bb_ref'] ) ? wp_unslash( $_GET['bb_ref'] ) : '';
    $bb_apt_ref        = sanitize_text_field( (string) $bb_raw_apt_ref );

    if ( 'paid' === $bb_checkout_state ) {
        $bb_show_state = 'paid';
    } elseif ( 'pending' === $bb_status || 'pending' === $bb_checkout_state ) {
        $bb_show_state = 'pending';
    } else {
        $bb_show_state = $bb_status;
    }
    ?>

    <?php if ( 'paid' === $bb_show_state ) : ?>
        <div class="bb-booking-notice bb-booking-paid">
            <p><?php esc_html_e( 'Payment received. Your appointment is confirmed — your receipt is shown below.', 'business-builder' ); ?></p>
            <?php if ( '' !== $bb_apt_ref ) : ?>
                <button type="button" class="bb-primary-button bb-notice-receipt-link" data-bb-receipt-open data-bb-receipt-auto data-bb-receipt-ref="<?php echo esc_attr( $bb_apt_ref ); ?>">
                    <?php esc_html_e( 'View Receipt', 'business-builder' ); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php elseif ( 'payment_error' === $bb_show_state ) : ?>
        <?php
        $bb_pay_reason = isset( $_GET['bb_pay_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['bb_pay_reason'] )) : '';
        $bb_pay_code   = isset( $_GET['bb_pay_code'] ) ? sanitize_key( wp_unslash( $_GET['bb_pay_code'] )) : '';
        ?>
        <div class="bb-booking-notice bb-booking-payment_error">
            <p><strong><?php esc_html_e( 'The payment could not be started.', 'business-builder' ); ?></strong></p>
            <?php if ( '' !== $bb_pay_reason ) : ?>
                <p><?php echo esc_html( $bb_pay_reason ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Please choose another payment method, or contact us so we can help.', 'business-builder' ); ?></p>
            <?php endif; ?>
            <?php if ( '' !== $bb_pay_code ) : ?>
                <p class="bb-booking-error-code"><code><?php echo esc_html( $bb_pay_code ); ?></code></p>
            <?php endif; ?>
        </div>
    <?php elseif ( '' !== $bb_show_state && isset( $bb_status_messages[ $bb_show_state ] )) : ?>
        <div class="bb-booking-notice bb-booking-<?php echo esc_attr( $bb_show_state ); ?>">
            <?php echo esc_html( $bb_status_messages[ $bb_show_state ] ); ?>
            <?php if ( 'pending' === $bb_show_state && '' !== $bb_apt_ref ) : ?>
                <button type="button" class="bb-primary-button bb-notice-receipt-link" data-bb-receipt-open data-bb-receipt-ref="<?php echo esc_attr( $bb_apt_ref ); ?>">
                    <?php esc_html_e( 'View Receipt', 'business-builder' ); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form
        class="bb-booking-form"
        method="post"
        action="<?php echo esc_url( BookingForm::action_url() ); ?>"
    >

        <input type="hidden" name="action" value="<?php echo esc_attr( BookingForm::action_name() ); ?>" />
        <input type="hidden" name="bb_page_id" value="<?php echo esc_attr( (string) $bb_page_id ); ?>" />
        <input type="hidden" name="bb_section_id" value="<?php echo esc_attr( $bb_section_id ); ?>" />
        <?php wp_nonce_field( BookingForm::nonce_action(), BookingForm::nonce_field() ); ?>

        <div class="bb-booking-grid">

            <p class="bb-booking-field">
                <label for="bb_client_name"><?php esc_html_e( 'Your Name', 'business-builder' ); ?> <span class="bb-required">*</span></label>
                <input type="text" name="bb_client_name" id="bb_client_name" required />
            </p>

            <p class="bb-booking-field">
                <label for="bb_client_phone"><?php esc_html_e( 'Phone', 'business-builder' ); ?></label>
                <input type="tel" name="bb_client_phone" id="bb_client_phone" />
            </p>

            <p class="bb-booking-field">
                <label for="bb_client_email"><?php esc_html_e( 'Email', 'business-builder' ); ?></label>
                <input type="email" name="bb_client_email" id="bb_client_email" />
            </p>

            <p class="bb-booking-field">
                <label for="bb_lawyer_id"><?php esc_html_e( 'Preferred Lawyer', 'business-builder' ); ?></label>
                <select name="bb_lawyer_id" id="bb_lawyer_id">
                    <option value="0"><?php esc_html_e( 'No preference', 'business-builder' ); ?></option>
                    <?php foreach ( $bb_lawyers as $bb_lawyer ) : ?>
                        <option value="<?php echo esc_attr( (string) $bb_lawyer->ID ); ?>">
                            <?php echo esc_html( $bb_lawyer->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="bb-booking-field">
                <label for="bb_practice_area"><?php esc_html_e( 'Practice Area', 'business-builder' ); ?></label>
                <select name="bb_practice_area" id="bb_practice_area">
                    <option value=""><?php esc_html_e( 'Select a practice area', 'business-builder' ); ?></option>
                    <?php foreach ( $bb_area_terms as $bb_area ) : ?>
                        <option value="<?php echo esc_attr( $bb_area->name ); ?>">
                            <?php echo esc_html( $bb_area->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="bb-booking-field">
                <label for="bb_type"><?php esc_html_e( 'Appointment Type', 'business-builder' ); ?></label>
                <select name="bb_type" id="bb_type">
                    <?php foreach ( AppointmentMeta::types() as $bb_type_slug => $bb_type_label ) : ?>
                        <option value="<?php echo esc_attr( $bb_type_slug ); ?>"><?php echo esc_html( $bb_type_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="bb-booking-field">
                <label for="bb_date"><?php esc_html_e( 'Date', 'business-builder' ); ?> <span class="bb-required">*</span></label>
                <input type="date" name="bb_date" id="bb_date" required />
            </p>

            <p class="bb-booking-field">
                <label for="bb_start"><?php esc_html_e( 'Time', 'business-builder' ); ?> <span class="bb-required">*</span></label>
                <input type="time" name="bb_start" id="bb_start" required />
                <span class="description"><?php esc_html_e( 'Availability is confirmed when you submit.', 'business-builder' ); ?></span>
            </p>

        </div>

        <p class="bb-booking-field">
            <label for="bb_notes"><?php esc_html_e( 'Notes', 'business-builder' ); ?></label>
            <textarea name="bb_notes" id="bb_notes" rows="4"></textarea>
        </p>

        <?php if ( $bb_payable ) : ?>

            <div class="bb-booking-payment" id="bb-booking-payment">

                <h3 class="bb-booking-payment-title">
                    <?php esc_html_e( 'Appointment Fee', 'business-builder' ); ?>
                </h3>

                <p class="bb-booking-payment-amount">
                    <?php echo esc_html( Currencies::format( (float) $bb_fee, $bb_currency, true ) ); ?>
                </p>

                <p class="bb-booking-payment-note">
                    <?php esc_html_e( 'Payment is required to confirm this appointment.', 'business-builder' ); ?>
                </p>

                <?php if ( count( $bb_gateways ) > 0 ) : ?>
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
                         * Per-gateway manual instructions (wallet / InstaPay /
                         * bank transfer). Revealed by JS only while its option
                         * is selected; shows the REAL configured details.
                         */
                        $bb_manual_tpl = BB_CORE_PATH . 'templates/partials/manual-payment-instructions.php';

                        if ( $bb_manual_tpl !== '' ) {
                            foreach ( $bb_gateways as $bb_gw_id => $bb_gw ) {

                                if ( ! $bb_gw->is_manual() ) {
                                    continue;
                                }

                                $bb_manual_gateway  = $bb_gw;
                                $bb_manual_gw_id    = (string) $bb_gw_id;
                                $bb_manual_amount   = $bb_fee;
                                $bb_manual_currency = $bb_currency;
                                $bb_manual_uid      = 'booking-' . sanitize_key( (string) $bb_gw_id );

                                include $bb_manual_tpl;
                            }
                        }
                        ?>

                        <p class="bb-payment-secure">
                            <span class="bb-payment-secure-icon" aria-hidden="true">&#128274;</span>
                            <?php esc_html_e( 'Your payment is processed securely by the provider. We never store card details.', 'business-builder' ); ?>
                        </p>
                    </fieldset>
                <?php elseif ( ! empty( $bb_pay['enabled'] )) : ?>
                    <div class="bb-payment-unconfigured" role="alert">
                        <?php esc_html_e( 'Payment is required for this appointment, but no payment method is available yet. Please contact us to complete your booking.', 'business-builder' ); ?>
                    </div>
                <?php endif; ?>

            </div>

        <?php endif; ?>

        <p class="bb-booking-submit">
            <button type="submit" class="bb-primary-button">
                <?php
                if ( $bb_payable ) {
                    esc_html_e( 'Continue to Payment', 'business-builder' );
                } else {
                    esc_html_e( 'Book Appointment', 'business-builder' );
                }
                ?>
            </button>
        </p>

    </form>

</div>