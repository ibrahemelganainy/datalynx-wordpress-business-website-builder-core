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
    'success' => __( 'Your appointment request has been received. We will confirm it shortly.', 'business-builder' ),
    'taken'   => __( 'Sorry, that time slot was just taken. Please choose another slot.', 'business-builder' ),
    'error'   => __( 'Sorry, your request could not be submitted. Please check the required fields.', 'business-builder' ),
);

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

        <p class="bb-booking-submit">
            <button type="submit" class="bb-primary-button">
                <?php esc_html_e( 'Book Appointment', 'business-builder' ); ?>
            </button>
        </p>

    </form>

</div>
