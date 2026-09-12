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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bb_areas = ConsultationForm::practice_areas();

$bb_status = isset( $_GET['bb_consult'] )
    ? sanitize_key( wp_unslash( $_GET['bb_consult'] ) )


?>

<div class="bb-consultation" id="bb-consultation-form">

    <?php if ( 'success' === $bb_status ) : ?>
        <div class="bb-consultation-notice bb-consultation-success">
            <?php esc_html_e( 'Thank you. Your consultation request has been received and our team will contact you shortly.', 'business-builder' ); ?>
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

        <p class="bb-consultation-submit">
            <button type="submit" class="bb-primary-button">
                <?php esc_html_e( 'Request a Consultation', 'business-builder' ); ?>
            </button>
        </p>

    </form>

</div>
