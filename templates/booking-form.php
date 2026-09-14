<?php
/**
 * Appointment booking form template (LawFirm pack).
 *
 * Rendered by LawFirmSections::render_booking_section(). Posts to
 * admin-post.php so it works for logged-out visitors. Availability is
 * enforced server-side by the Availability service; the slot list here is
 * a convenience only.
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Packs\LawFirm\Appointments\BookingForm;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Core\Payments\Currencies;

if ( ! defined( 'ABSPATH' ) ) {
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
    'pending'       => __( 'Your appointment was held. Please complete the payment below to confirm it.', 'business-builder' ),
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

if ( is_wp_error( $bb_area_terms ) || ! is_array( $bb_area_terms ) ) {
    $bb_area_terms = array();
}

?>

<div class="bb-booking" id="bb-booking-form">

    <?php if ( '' !== $bb_status && isset( $bb_status_messages[ $bb_status ] ) ) : ?>
        <div class="bb-booking-notice bb-booking-<?php echo esc_attr( $bb_status ); ?>">
            <?php echo esc_html( $bb_status_messages[ $bb_status ] ); ?>
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
