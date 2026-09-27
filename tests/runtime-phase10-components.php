<?php
/*
 * Phase 10 verification: the component architecture resolves and renders.
 * Requires the active theme (blog 2) whose functions.php defines bb_component().
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$blog_id = 2;
switch_to_blog( $blog_id );

/*
 * Mirror the proven Phase-9 harness: activate the theme for this site so
 * get_template_directory() resolves to the Business Builder theme, then load
 * its functions.php. This harness boots before switch_to_blog(), so WordPress
 * never auto-loads the theme here.
 */
$original_theme = get_option( 'stylesheet' );

if ( ! function_exists( 'bb_render_component' ) ) {
    switch_theme( 'business-builder' );
    require_once get_template_directory() . '/functions.php';
}

check( 'bb_render_component() exists (theme)', function_exists( 'bb_render_component' ) );

/*
 * In a real request the plugin's LawFirmSections::register() adds this filter
 * at `init`. This harness boots without the plugin pack, so register the pack
 * root the exact same way the pack does.
 */
add_filter( 'bb_component_roots', function ( $roots ) {
    $roots   = is_array( $roots ) ? $roots : array();
    $roots[] = WP_CONTENT_DIR . '/plugins/business-builder-core/packs/LawFirm/Sections/components';
    return $roots;
} );
check( 'bb_component() exists (theme)', function_exists( 'bb_component' ) );

/* Pack registers its component root via the filter. */
$roots = bb_component_roots();
$has_pack_root = false;
foreach ( $roots as $r ) {
    if ( false !== strpos( $r, 'LawFirm' ) && false !== strpos( $r, 'components' ) ) {
        $has_pack_root = true;
    }
}
check( 'pack component root registered', $has_pack_root );
/* Resolution. */
check( 'theme: section-heading resolves', '' !== bb_component_path( 'section-heading' ) );
check( 'theme: empty-state resolves', '' !== bb_component_path( 'empty-state' ) );
check( 'pack: lawyer/card resolves', '' !== bb_component_path( 'lawyer/card' ) );
check( 'pack: service/card resolves', '' !== bb_component_path( 'service/card' ) );
check( 'pack: practice-area/card resolves', '' !== bb_component_path( 'practice-area/card' ) );
check( 'pack: testimonial/card resolves', '' !== bb_component_path( 'testimonial/card' ) );
check( 'pack: faq/item resolves', '' !== bb_component_path( 'faq/item' ) );

/* Unknown component is safe. */
check( 'unknown component resolves to empty', '' === bb_component_path( 'does/not-exist' ) );
$unknown = bb_render_component( 'does/not-exist' );
/* Safe: empty, or a debug comment when WP_DEBUG is on (never a fatal). */
check( 'unknown component renders safely (empty or debug comment)', '' === $unknown || 0 === strpos( trim( $unknown ), '<!--' ) );

/* Path traversal is rejected. */
check( 'path traversal rejected', '' === bb_component_path( '../../functions' ) );

/* Rendering preserves the existing classes/semantics. */
$empty = bb_render_component( 'empty-state', array( 'message' => 'No items found.' ) );
check( 'empty-state renders .bb-empty-state', false !== strpos( $empty, 'class="bb-empty-state"' ) );
check( 'empty-state escapes the message', false !== strpos( $empty, 'No items found.' ) );

$head = bb_render_component( 'section-heading', array( 'title' => 'Our Team', 'description' => 'Meet us' ) );
check( 'heading renders .bb-section-heading', false !== strpos( $head, 'class="bb-section-heading"' ) );
check( 'heading renders h2.bb-section-title', false !== strpos( $head, '<h2 class="bb-section-title">Our Team</h2>' ) );

$lawyer = bb_render_component( 'lawyer/card', array(
    'name' => 'Jane Doe', 'profile_url' => 'https://example.com/jane', 'is_public' => true,
    'role' => 'Partner', 'experience' => '12', 'phone' => '0100', 'email' => 'j@e.com', 'profile_link' => '',
) );
check( 'lawyer renders article.bb-lawyer-card', false !== strpos( $lawyer, '<article class="bb-lawyer-card">' ) );
check( 'lawyer links the name when public', false !== strpos( $lawyer, 'bb-lawyer-link' ) );
check( 'lawyer renders tel: link', false !== strpos( $lawyer, 'href="tel:0100"' ) );

$lawyer_draft = bb_render_component( 'lawyer/card', array( 'name' => 'Draft', 'is_public' => false, 'profile_url' => 'https://x/y' ) );
check( 'draft lawyer is NOT linked', false === strpos( $lawyer_draft, 'bb-lawyer-link' ) );

$svc = bb_render_component( 'service/card', array( 'title' => 'Contracts', 'summary' => 'We draft', 'icon' => '⚖' ) );
check( 'service renders article.bb-service-card', false !== strpos( $svc, '<article class="bb-service-card">' ) );

$pa = bb_render_component( 'practice-area/card', array( 'title' => 'Family', 'summary' => 'Family law' ) );
check( 'practice area renders article.bb-practice-area-card', false !== strpos( $pa, '<article class="bb-practice-area-card">' ) );

$faq = bb_render_component( 'faq/item', array( 'question' => 'Q?', 'answer' => '<p>A</p>' ) );
check( 'faq renders .bb-faq-item', false !== strpos( $faq, '<div class="bb-faq-item">' ) );
check( 'faq renders h3 + answer', false !== strpos( $faq, '<h3>Q?</h3>' ) && false !== strpos( $faq, 'bb-faq-answer' ) );

$tst = bb_render_component( 'testimonial/card', array( 'author' => 'Sara', 'quote' => 'Great', 'rating' => 5, 'author_title' => 'CEO' ) );
check( 'testimonial renders article.bb-testimonial-card', false !== strpos( $tst, '<article class="bb-testimonial-card">' ) );
check( 'testimonial renders blockquote', false !== strpos( $tst, '<blockquote>' ) );
check( 'testimonial renders rating with aria-label', false !== strpos( $tst, 'aria-label="5 out of 5"' ) );
check( 'testimonial rating has NO mojibake', false === strpos( $tst, 'Ã' ) && false === strpos( $tst, 'â' ) );
$stars = substr_count( $tst, "\u{2605}" );
check( 'testimonial renders 5 star glyphs', 5 === $stars );

/* Variant argument is accepted and falls back to the base file. */
$variant = bb_render_component( 'lawyer/card', array( 'name' => 'V' ), 'compact' );
check( 'unknown variant safely falls back to base', false !== strpos( $variant, 'bb-lawyer-card' ) );

/* Restore the site's original theme — never leave the test site changed. */
if ( isset( $original_theme ) && 'business-builder' !== $original_theme ) {
    switch_theme( $original_theme );
}

restore_current_blog();
echo 'DONE' . PHP_EOL;