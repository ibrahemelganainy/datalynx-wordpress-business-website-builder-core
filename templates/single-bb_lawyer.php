<?php
/**
 * Single Lawyer profile template (LawFirm pack).
 *
 * Rendered by LawyerProfile::template() when the active theme does
 * not provide its own single-bb_lawyer.php. All data comes from
 * LawyerProfile::get_data() so meta-key knowledge stays in PHP.
 *
 * Theme-compatible: calls get_header() / get_footer() so it wraps
 * correctly inside any WordPress theme.
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$bb_lawyer_id = get_the_ID();
$bb_lawyer = $bb_lawyer_id ? LawyerProfile::get_data( $bb_lawyer_id ) : array();
$bb_is_available = $bb_lawyer_id ? LawyerProfile::is_active( (int) $bb_lawyer_id ) : false;

if ( empty( $bb_lawyer ) || ! $bb_is_available ) {
    get_footer();
    return;
}

$bb_initial = $bb_lawyer['name'] !== ''
    ? mb_substr( (string) $bb_lawyer['name'], 0, 1 )
    : '?';

?>

<div class="bb-template bb-template-default bb-lawyer-profile">

    <section class="bb-section bb-section-lawyer-profile">

        <div class="bb-section-inner">

            <div class="bb-lawyer-profile-hero">

                <div class="bb-lawyer-profile-media">

                    <?php if ( $bb_lawyer['photo_id'] ) : ?>

                        <?php
                        echo wp_get_attachment_image(
                            $bb_lawyer['photo_id'],
                            'large',
                            false,
                            array( 'class' => 'bb-lawyer-profile-image' )
                        );
                        ?>

                    <?php else : ?>

                        <div class="bb-lawyer-profile-image bb-lawyer-profile-placeholder">
                            <span><?php echo esc_html( $bb_initial ); ?></span>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="bb-lawyer-profile-intro">

                    <h1 class="bb-lawyer-profile-name">
                        <?php echo esc_html( $bb_lawyer['name'] ); ?>
                    </h1>

                    <?php if ( '' !== $bb_lawyer['title'] ) : ?>
                        <p class="bb-lawyer-profile-role">
                            <?php echo esc_html( $bb_lawyer['title'] ); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ( ! empty( $bb_lawyer['practice_areas'] ) ) : ?>
                        <div class="bb-lawyer-profile-areas">
                            <?php foreach ( $bb_lawyer['practice_areas'] as $bb_term ) : ?>
                                <a
                                    class="bb-lawyer-profile-area"
                                    href="<?php echo esc_url( get_term_link( $bb_term ) ); ?>"
                                >
                                    <?php echo esc_html( $bb_term->name ); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( '' !== $bb_lawyer['short_bio'] ) : ?>
                        <p class="bb-lawyer-profile-lede">
                            <?php echo esc_html( $bb_lawyer['short_bio'] ); ?>
                        </p>
                    <?php endif; ?>

                    <div class="bb-lawyer-profile-contact">

                        <?php if ( '' !== $bb_lawyer['phone'] ) : ?>
                            <a class="bb-primary-button" href="tel:<?php echo esc_attr( $bb_lawyer['phone'] ); ?>">
                                <?php esc_html_e( 'Call', 'business-builder' ); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ( '' !== $bb_lawyer['whatsapp'] ) : ?>
                            <a
                                class="bb-primary-button bb-primary-button-light"
                                href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $bb_lawyer['whatsapp'] ) ); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php esc_html_e( 'WhatsApp', 'business-builder' ); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ( '' !== $bb_lawyer['email'] ) : ?>
                            <a class="bb-primary-button bb-primary-button-light" href="mailto:<?php echo esc_attr( $bb_lawyer['email'] ); ?>">
                                <?php esc_html_e( 'Email', 'business-builder' ); ?>
                            </a>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <div class="bb-lawyer-profile-body">

                <div class="bb-lawyer-profile-main">

                    <?php if ( '' !== $bb_lawyer['full_bio'] ) : ?>

                        <div class="bb-lawyer-profile-section">
                            <h2><?php esc_html_e( 'Biography', 'business-builder' ); ?></h2>
                            <div class="bb-lawyer-profile-bio">
                                <?php echo wp_kses_post( wpautop( $bb_lawyer['full_bio'] ) ); ?>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

                <aside class="bb-lawyer-profile-sidebar">

                    <?php if ( '' !== $bb_lawyer['experience'] ) : ?>
                        <div class="bb-lawyer-profile-fact">
                            <strong><?php esc_html_e( 'Years of Experience', 'business-builder' ); ?></strong>
                            <span><?php echo esc_html( $bb_lawyer['experience'] ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ( '' !== $bb_lawyer['license_number'] ) : ?>
                        <div class="bb-lawyer-profile-fact">
                            <strong><?php esc_html_e( 'License Number', 'business-builder' ); ?></strong>
                            <span><?php echo esc_html( $bb_lawyer['license_number'] ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ( '' !== $bb_lawyer['languages'] ) : ?>
                        <div class="bb-lawyer-profile-fact">
                            <strong><?php esc_html_e( 'Languages', 'business-builder' ); ?></strong>
                            <span><?php echo esc_html( $bb_lawyer['languages'] ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ( '' !== $bb_lawyer['education'] ) : ?>
                        <div class="bb-lawyer-profile-fact">
                            <strong><?php esc_html_e( 'Education', 'business-builder' ); ?></strong>
                            <span><?php echo esc_html( $bb_lawyer['education'] ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php
                    /*
                     * Only treat a stored social value as a link when it is a
                     * real URL. A bare handle saved before save-time validation
                     * must not become a dead "http://handle" link.
                     */
                    $bb_social = array(
                        'linkedin' => \BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields::is_external_url( (string) $bb_lawyer['linkedin'] ) ? (string) $bb_lawyer['linkedin'] : '',
                        'facebook' => \BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields::is_external_url( (string) $bb_lawyer['facebook'] ) ? (string) $bb_lawyer['facebook'] : '',
                        'x'        => \BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields::is_external_url( (string) $bb_lawyer['x'] ) ? (string) $bb_lawyer['x'] : '',
                    );
                    ?>

                    <?php if ( '' !== $bb_social['linkedin'] || '' !== $bb_social['facebook'] || '' !== $bb_social['x'] ) : ?>
                        <div class="bb-lawyer-profile-socials">

                            <?php if ( '' !== $bb_social['linkedin'] ) : ?>
                                <a href="<?php echo esc_url( $bb_social['linkedin'] ); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                            <?php endif; ?>

                            <?php if ( '' !== $bb_social['facebook'] ) : ?>
                                <a href="<?php echo esc_url( $bb_social['facebook'] ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
                            <?php endif; ?>

                            <?php if ( '' !== $bb_social['x'] ) : ?>
                                <a href="<?php echo esc_url( $bb_social['x'] ); ?>" target="_blank" rel="noopener noreferrer">X</a>
                            <?php endif; ?>

                        </div>
                    <?php endif; ?>

                </aside>

            </div>

        </div>

    </section>

</div>

<?php
get_footer();
