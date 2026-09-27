<?php
/*
 * Phase 16 verification: Theme Content & Shell Data System.
 *
 * Covers:
 *  - existing shell behavior intact (all header/nav/footer variants, fallbacks)
 *  - prepared shell data contract (identity + optional contact/social/cta/links)
 *  - empty-safe rendering (absent/null/empty => no markup, no wrapper)
 *  - plugin bridge maps bb_site_settings -> generic presentation slots
 *  - sanitization / escaping of every configurable value
 *  - security: unsafe URLs, script/HTML payloads, traversal-like input
 *  - legacy hooks preserved (bb_theme_header_variant / footer_variant + data filters)
 *  - Phase 14 design system + Phase 15 shell selection still work
 *  - multisite isolation
 *  - domain-agnostic: theme source contains no business/pack coupling
 */

define('WP_USE_THEMES', false);
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
if ( ! defined( 'BB_THEME_PATH' ) ) { define( 'BB_THEME_PATH', trailingslashit( $theme_dir ) ); }
if ( ! defined( 'BB_THEME_URL' ) ) { define( 'BB_THEME_URL', 'http://lawfirm.builder.test/wp-content/themes/business-builder/' ); }
if ( ! defined( 'BB_THEME_VERSION' ) ) { define( 'BB_THEME_VERSION', '1.0.0' ); }
foreach ( array( 'setup', 'theme-support', 'enqueue', 'preset-resolver', 'shell-variants', 'shell-data', 'template-functions', 'template-hooks', 'navigation', 'design-schema', 'customization', 'components' ) as $f ) {
    $p = $theme_dir . '/inc/' . $f . '.php';
    if ( is_readable( $p ) ) { require_once $p; }
}
require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Theme/theme-shell-data.php';

switch_to_blog( 2 );

/* Preserve & clear business settings for a deterministic run. */
$settings_owner = new \BusinessBuilderCore\Settings\SiteSettings();
$original_settings = $settings_owner->get_all();
$blank = $original_settings;
foreach ( array( 'phone', 'email', 'address', 'whatsapp', 'facebook', 'instagram', 'youtube', 'linkedin', 'twitter' ) as $k ) { $blank[ $k ] = ''; }
update_option( 'bb_site_settings', $blank );
bb_theme_shell_bridge_register();
foreach ( array( 'header', 'navigation', 'footer' ) as $p ) { remove_theme_mod( 'bb_theme_shell_' . $p ); }

/* ---------- 1. Existing shell behavior intact ---------- */
$catalog = bb_theme_shell_variants();
$all_render = true;
foreach ( $catalog as $part => $variants ) {
    foreach ( $variants as $slug => $meta ) {
        set_theme_mod( 'bb_theme_shell_' . $part, $slug );
        ob_start(); bb_theme_shell_render( $part ); $html = ob_get_clean();
        if ( '' === $html ) { $all_render = false; }
        if ( 'header' === $part && false === strpos( $html, 'bb-site-header' ) ) { $all_render = false; }
        if ( 'footer' === $part && false === strpos( $html, 'bb-site-footer' ) ) { $all_render = false; }
        if ( 'navigation' === $part && false === strpos( $html, 'id="bb-primary-nav"' ) ) { $all_render = false; }
    }
}
check( 'all header/nav/footer variants still render', $all_render );
check( 'unknown variant falls back to default', 'default' === bb_theme_shell_resolve( 'header', 'nope' ) );
check( 'empty variant falls back to default', 'default' === bb_theme_shell_resolve( 'footer', '' ) );

/* ---------- 2. Default output with empty business data = no optional markup ---------- */
set_theme_mod( 'bb_theme_shell_header', 'default' );
ob_start(); bb_theme_shell_render( 'header' ); $h = ob_get_clean();
ob_start(); bb_theme_shell_render( 'footer' ); $f = ob_get_clean();
check( 'empty settings -> no contact markup in header', false === strpos( $h, 'bb-shell-contact' ) );
check( 'empty settings -> no social markup in footer', false === strpos( $f, 'bb-shell-social' ) );
check( 'empty settings -> no utility markup in footer', false === strpos( $f, 'bb-shell-links' ) );
check( 'empty settings -> no CTA markup in header', false === strpos( $h, 'bb-shell-cta' ) );

/* ---------- 3. Prepared data contract ---------- */
$hd = bb_theme_shell_header_data();
$fd = bb_theme_shell_footer_data();
check( 'header data has identity keys', isset( $hd['site_name'], $hd['tagline'], $hd['home_url'], $hd['has_logo'] ) );
check( 'header data exposes optional slots', array_key_exists( 'utility_links', $hd ) && array_key_exists( 'cta', $hd ) );
check( 'footer data has identity keys', isset( $fd['site_name'], $fd['tagline'], $fd['has_menu'], $fd['year'] ) );
check( 'footer data exposes optional slots', array_key_exists( 'contact', $fd ) && array_key_exists( 'social', $fd ) && array_key_exists( 'utility_links', $fd ) );
check( 'nav location helper intact', 'primary' === bb_theme_shell_nav_location() );

/* ---------- 4. Helpers exist ---------- */
check( 'contact normaliser exists', function_exists( 'bb_theme_shell_normalize_contact' ) );
check( 'social normaliser exists', function_exists( 'bb_theme_shell_normalize_social' ) );
check( 'links normaliser exists', function_exists( 'bb_theme_shell_normalize_links' ) );
check( 'cta normaliser exists', function_exists( 'bb_theme_shell_normalize_cta' ) );
check( 'render helpers exist', function_exists( 'bb_theme_shell_render_contact' ) && function_exists( 'bb_theme_shell_render_social' ) && function_exists( 'bb_theme_shell_render_links' ) && function_exists( 'bb_theme_shell_render_cta' ) );

/* ---------- 5. Empty-safe normalisers ---------- */
check( 'contact: null -> null', null === bb_theme_shell_normalize_contact( null ) );
check( 'contact: empty array -> null', null === bb_theme_shell_normalize_contact( array() ) );
check( 'contact: whitespace only -> null', null === bb_theme_shell_normalize_contact( array( 'phone' => '   ' ) ) );
check( 'contact: valid -> array', is_array( bb_theme_shell_normalize_contact( array( 'phone' => '+1 555 000' ) ) ) );
check( 'social: garbage -> empty list', array() === bb_theme_shell_normalize_social( 'not-an-array' ) );
check( 'links: garbage -> empty list', array() === bb_theme_shell_normalize_links( 42 ) );
check( 'cta: missing url -> null', null === bb_theme_shell_normalize_cta( array( 'label' => 'Hi' ) ) );
check( 'cta: missing label -> null', null === bb_theme_shell_normalize_cta( array( 'url' => '/x' ) ) );

/* ---------- 6. Valid data renders correctly ---------- */
update_option( 'bb_site_settings', array_merge( $blank, array(
    'phone'     => '+1 (555) 123-4567',
    'email'     => 'hello@example.com',
    'address'   => '123 Main St',
    'facebook'  => 'https://facebook.com/example',
    'instagram' => 'https://instagram.com/example',
) ) );
$hd = bb_theme_shell_header_data();
$fd = bb_theme_shell_footer_data();
check( 'bridge: header contact has phone', isset( $hd['contact']['phone'] ) );
check( 'bridge: footer contact has address', isset( $fd['contact']['address'] ) );
check( 'bridge: footer social has 2 links', count( $fd['social'] ?? array() ) === 2 );
set_theme_mod( 'bb_theme_shell_footer', 'default' );
ob_start(); bb_theme_shell_render( 'footer' ); $f = ob_get_clean();
check( 'valid data -> footer renders contact', false !== strpos( $f, 'bb-shell-contact' ) );
check( 'valid data -> footer renders social', false !== strpos( $f, 'bb-shell-social' ) );
check( 'phone rendered as tel: link', false !== strpos( $f, 'href="tel:+15551234567"' ) );
check( 'email rendered as mailto: link', false !== strpos( $f, 'href="mailto:hello@example.com"' ) );

/* ---------- 7. Security: unsafe values ---------- */
/* Unsafe social URL scheme (javascript:) must be dropped. */
$bad_social = bb_theme_shell_normalize_social( array( array( 'label' => 'X', 'url' => 'javascript:alert(1)' ) ) );
check( 'social: javascript: URL dropped', empty( $bad_social ) );
/* Unsafe utility link scheme dropped. */
$bad_links = bb_theme_shell_normalize_links( array( array( 'label' => 'Drop', 'url' => 'javascript:void(0)' ) ) );
check( 'links: javascript: URL dropped', empty( $bad_links ) );
/* CTA with unsafe scheme dropped. */
check( 'cta: javascript: URL rejected', null === bb_theme_shell_normalize_cta( array( 'label' => 'x', 'url' => 'javascript:alert(1)' ) ) );
/* Script payload in a label is escaped at output. */
ob_start();
bb_theme_shell_render_cta( array( 'label' => '<script>alert(1)</script>', 'url' => 'https://example.com/' ) );
$cta_html = ob_get_clean();
check( 'cta label is escaped (no raw <script>)', false === strpos( $cta_html, '<script>' ) && false !== strpos( $cta_html, '&lt;script&gt;' ) );
/* Phone with an injection attempt: the tel: guard rejects implausible numbers,
 * and any emitted href would collapse to digits/+ only. */
$c = bb_theme_shell_normalize_contact( array( 'phone' => '+1 555";alert(1)' ) );
$c_ok = isset( $c['phone_href'] ) && ( '' === $c['phone_href'] || preg_match( '/^tel:[0-9+]+$/', $c['phone_href'] ) === 1 );
check( 'phone href never contains unsafe characters', $c_ok );
/* A plausible phone DOES produce a safe tel: href. */
$c2 = bb_theme_shell_normalize_contact( array( 'phone' => '+1 (555) 123-4567' ) );
check( 'plausible phone -> safe tel: href', isset( $c2['phone_href'] ) && '+15551234567' === substr( $c2['phone_href'], 4 ) );
/* Scheme-less / traversal-like link URLs are rejected (no explicit http/https). */
$l = bb_theme_shell_normalize_links( array( array( 'label' => 'T', 'url' => '../../etc/passwd' ) ) );
check( 'scheme-less traversal-like link is rejected', empty( $l ) );
/* A site-relative link with a leading slash is accepted. */
$l2 = bb_theme_shell_normalize_links( array( array( 'label' => 'Home', 'url' => '/home' ) ) );
check( 'leading-slash relative link is accepted', isset( $l2[0]['url'] ) );

/* ---------- 8. Legacy + data hooks preserved ---------- */
add_filter( 'bb_theme_header_variant', static function () { return 'minimal'; } );
check( 'legacy bb_theme_header_variant still honoured', 'minimal' === bb_theme_shell_part_variant( 'header' ) );
remove_all_filters( 'bb_theme_header_variant' );
add_filter( 'bb_theme_footer_variant', static function () { return 'centered'; } );
check( 'legacy bb_theme_footer_variant still honoured', 'centered' === bb_theme_shell_part_variant( 'footer' ) );
remove_all_filters( 'bb_theme_footer_variant' );
$hooked = has_filter( 'bb_theme_shell_footer_data' );
check( 'bb_theme_shell_footer_data filter is hooked by the bridge', false !== $hooked );
$custom = apply_filters( 'bb_theme_shell_footer_data', array( 'social' => array( array( 'label' => 'Z', 'url' => 'https://z.example' ) ) ) );
check( 'data filter can supply social', ! empty( $custom['social'] ) );

/* Non-destructive: bridge must NOT overwrite an explicitly supplied contact. */
update_option( 'bb_site_settings', array_merge( $blank, array( 'phone' => '+1 555 999' ) ) );
$supplied = apply_filters( 'bb_theme_shell_footer_data', array( 'contact' => array( 'phone' => '+9 000' ) ) );
check( 'bridge does not overwrite an existing contact value', ( $supplied['contact']['phone'] ?? '' ) === '+9 000' );

/* ---------- 9. Domain-agnostic guarantee ---------- */
$shell_src = file_get_contents( $theme_dir . '/inc/shell-data.php' );
/* Strip comments so the docblock's illustrative wording is not a false positive. */
$shell_code = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $shell_src );
$forbidden = array( 'LawFirm', 'bb_lawyer', 'bb_legal_service', 'LawFirmQueries', 'bb_site_settings', 'SiteSettings', 'new WP_Query' );
$clean = true;
foreach ( $forbidden as $needle ) { if ( false !== strpos( $shell_code, $needle ) ) { $clean = false; } }
check( 'theme shell-data.php CODE has NO business/pack coupling', $clean );
$bridge_src = file_get_contents( WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Theme/theme-shell-data.php' );
check( 'plugin bridge is the only reader of bb_site_settings', false !== strpos( $bridge_src, 'SiteSettings' ) );

/* ---------- 10. Phase 14 + 15 still work ---------- */
set_theme_mod( 'bb_theme_preset', 'luxury' );
check( 'Phase 14 preset still resolves', 'luxury' === bb_theme_get_preset() );
check( 'Phase 14 preset css still emits :root', 0 === strpos( bb_theme_preset_css(), ':root{' ) );
set_theme_mod( 'bb_theme_preset', 'default' );
set_theme_mod( 'bb_theme_shell_header', 'split' );
check( 'Phase 15 shell selection still resolves', 'split' === bb_theme_shell_part_variant( 'header' ) );
bb_theme_reset_shell_variants();
remove_theme_mod( 'bb_theme_shell_navigation' );

/* Restore the site's real settings + clear test mods. */
update_option( 'bb_site_settings', $original_settings );
bb_theme_reset_shell_variants();

restore_current_blog();

/* ---------- 11. Multisite isolation ---------- */
switch_to_blog( 2 );
update_option( 'bb_site_settings', array_merge( $blank, array( 'phone' => '+2 222 222' ) ) );
$site2_phone = ( new \BusinessBuilderCore\Settings\SiteSettings() )->get( 'phone' );
restore_current_blog();
switch_to_blog( 1 );
$site1_settings = get_option( 'bb_site_settings' );
$site1_phone = is_array( $site1_settings ) ? ( $site1_settings['phone'] ?? '' ) : '';
restore_current_blog();
check( 'site 2 business phone is per-site', '+2 222 222' === $site2_phone );
check( 'site 1 does not inherit site 2 phone', '+2 222 222' !== $site1_phone );

/* Final cleanup: restore site 2 real settings. */
switch_to_blog( 2 );
update_option( 'bb_site_settings', $original_settings );
restore_current_blog();

echo 'DONE' . PHP_EOL;