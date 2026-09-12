<?php
/**
 * Private consultation / appointment status page template (LawFirm pack).
 *
 * Rendered by the [bb_consultation_status] shortcode
 * (BusinessBuilderCore\Packs\LawFirm\Frontend\StatusPage). The shortcode
 * itself builds the escaped markup; this template is provided so a site
 * owner can register the shortcode on a page inside the Builder and get
 * a styled, self-contained status screen.
 *
 * Security: only the public, non-sequential reference is ever accepted
 * or displayed. No internal ids or private contact fields are shown.
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Packs\LawFirm\Frontend\StatusPage;

defined( 'ABSPATH' ) || exit;

$bb_reference = isset( $_GET[ StatusPage::QUERY_ARG ] )
    ? sanitize_text_field( wp_unslash( $_GET[ StatusPage::QUERY_ARG ] ) )
    : '';

?>
<div class="bb-status-page">

    <h2 class="bb-status-page-title">
        <?php esc_html_e( 'Check Your Request Status', 'business-builder' ); ?>
    </h2>

    <p class="bb-status-page-intro">
        <?php esc_html_e( 'Enter the reference number you received to see the current status of your request and payment.', 'business-builder' ); ?>
    </p>

    <?php
    /*
     * The shortcode renders the form + (when a reference is present and
     * valid) the escaped status summary. It never echoes raw input.
     */
    echo do_shortcode( '[' . StatusPage::SHORTCODE . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>

</div>
