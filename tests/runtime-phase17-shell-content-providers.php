<?php
/*
 * Phase 17 verification: Shell Content Owner Slots / Pack Shell-Data Providers.
 *
 * The audit concluded that NO LawFirm provider is justified yet (no authoritative
 * domain-level CTA/utility source exists). This suite therefore proves the two
 * things that make that a CORRECT outcome:
 *
 *   (1) The no-provider site behaves exactly like Phase 16 (nothing appears).
 *   (2) The architecture is READY: an external provider (standing in for a future
 *       LawFirm provider) can contribute cta/utility_links through the EXISTING
 *       filters with ZERO Theme changes - and unsafe input is rejected/escaped.
 *
 * It also re-asserts every Phase 17 invariant (Theme domain-agnostic, SiteSettings
 * ownership preserved, non-destructive merge, empty-safe, variants intact,
 * multisite isolation).
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

$owner = new \BusinessBuilderCore\Settings\SiteSettings();
$original_settings = $owner->get_all();

/* Deterministic baseline: clear business contact/social, no shell mods. */
$blank = $original_settings;
foreach ( array( 'phone', 'email', 'address', 'whatsapp', 'facebook', 'instagram', 'youtube', 'linkedin', 'twitter' ) as $k ) { $blank[ $k ] = ''; }
update_option( 'bb_site_settings', $blank );
foreach ( array( 'header', 'navigation', 'footer' ) as $p ) { remove_theme_mod( 'bb_theme_shell_' . $p ); }
remove_all_filters( 'bb_theme_shell_header_data' );
remove_all_filters( 'bb_theme_shell_footer_data' );
bb_theme_shell_bridge_register();

/* =====================================================================
 * A. No-provider baseline == Phase 16 behavior (the correct P17 outcome)
 * ===================================================================== */

/* The pack is loaded only when bb_business_type = law_firm (PackManager).
 * On this site it IS active, but no LawFirm shell provider is registered by
 * design. Confirm nothing domain-specific is contributed. */
$header = bb_theme_shell_header_data();
$footer = bb_theme_shell_footer_data();
check( 'no provider: header.cta is empty', empty( $header['cta'] ) );
check( 'no provider: header.utility_links is empty', empty( $header['utility_links'] ) );
check( 'no provider: footer.utility_links is empty', empty( $footer['utility_links'] ) );
check( 'no provider: footer.contact is empty (no business data)', empty( $footer['contact'] ) );
check( 'no provider: footer.social is empty (no business data)', empty( $footer['social'] ) );

set_theme_mod( 'bb_theme_shell_header', 'default' );
set_theme_mod( 'bb_theme_shell_footer', 'default' );
ob_start(); bb_theme_shell_render( 'header' ); $h = ob_get_clean();
ob_start(); bb_theme_shell_render( 'footer' ); $f = ob_get_clean();
check( 'no provider: no CTA markup in header', false === strpos( $h, 'bb-shell-cta' ) );
check( 'no provider: no utility markup in header', false === strpos( $h, 'bb-shell-links' ) );
check( 'no provider: no utility markup in footer', false === strpos( $f, 'bb-shell-links' ) );
check( 'no provider: no contact markup in footer', false === strpos( $f, 'bb-shell-contact' ) );
check( 'no provider: no social markup in footer', false === strpos( $f, 'bb-shell-social' ) );

/* =====================================================================
 * B. Architecture is READY: an external provider contributes via the
 *    EXISTING filters with ZERO Theme change.
 * ===================================================================== */

/*
 * A stand-in external provider. In a future phase a LawFirm provider would
 * do exactly this - subscribe to the Theme filters and add generic data.
 */
add_filter( 'bb_theme_shell_header_data', static function ( $data ) {
    $data['cta'] = array( 'label' => __( 'Book a Consultation', 'business-builder' ), 'url' => 'https://example.com/booking' );
    $data['utility_links'] = array( array( 'label' => __( 'Client Portal', 'business-builder' ), 'url' => 'https://example.com/portal' ) );
    return $data;
}, 20, 1 );
add_filter( 'bb_theme_shell_footer_data', static function ( $data ) {
    $data['utility_links'] = array( array( 'label' => __( 'Privacy', 'business-builder' ), 'url' => 'https://example.com/privacy' ) );
    return $data;
}, 20, 1 );

$header = bb_theme_shell_header_data();
$footer = bb_theme_shell_footer_data();
check( 'provider: header.cta populated', ! empty( $header['cta']['url'] ) && ! empty( $header['cta']['label'] ) );
check( 'provider: header.utility_links populated', count( $header['utility_links'] ?? array() ) === 1 );
check( 'provider: footer.utility_links populated', count( $footer['utility_links'] ?? array() ) === 1 );

/* Rendered through EVERY header variant + EVERY footer variant. */
$header_variants = array_keys( bb_theme_shell_variants()['header'] );
$footer_variants = array_keys( bb_theme_shell_variants()['footer'] );
$hdr_all = true;
foreach ( $header_variants as $v ) {
    set_theme_mod( 'bb_theme_shell_header', $v );
    ob_start(); bb_theme_shell_render( 'header' ); $html = ob_get_clean();
    if ( false === strpos( $html, 'bb-shell-cta' ) || false === strpos( $html, 'https://example.com/booking' ) ) { $hdr_all = false; }
}
check( 'provider CTA appears in ALL header variants', $hdr_all );

$ftr_all = true;
foreach ( $footer_variants as $v ) {
    set_theme_mod( 'bb_theme_shell_footer', $v );
    ob_start(); bb_theme_shell_render( 'footer' ); $html = ob_get_clean();
    if ( false === strpos( $html, 'bb-shell-links' ) || false === strpos( $html, 'https://example.com/privacy' ) ) { $ftr_all = false; }
}
check( 'provider utility links appear in ALL footer variants', $ftr_all );

/* Removing the provider returns the shell to the no-provider state. */
remove_all_filters( 'bb_theme_shell_header_data' );
remove_all_filters( 'bb_theme_shell_footer_data' );
bb_theme_shell_bridge_register();
set_theme_mod( 'bb_theme_shell_header', 'default' );
set_theme_mod( 'bb_theme_shell_footer', 'default' );
ob_start(); bb_theme_shell_render( 'header' ); $h = ob_get_clean();
check( 'provider removal -> header CTA gone again', false === strpos( $h, 'bb-shell-cta' ) );
check( 'provider removal -> header data cta empty', empty( bb_theme_shell_header_data()['cta'] ) );

/* =====================================================================
 * C. Safety: unsafe provider data is rejected / escaped
 * ===================================================================== */

/* javascript: CTA rejected by the Theme normaliser. */
check( 'unsafe CTA scheme rejected', null === bb_theme_shell_normalize_cta( array( 'label' => 'x', 'url' => 'javascript:alert(1)' ) ) );
check( 'data: CTA scheme rejected', null === bb_theme_shell_normalize_cta( array( 'label' => 'x', 'url' => 'data:text/html;base64,AAAA' ) ) );
check( 'CTA without label rejected', null === bb_theme_shell_normalize_cta( array( 'url' => 'https://x.example' ) ) );
check( 'CTA without url rejected', null === bb_theme_shell_normalize_cta( array( 'label' => 'x' ) ) );

/* Unsafe target is not honoured (only _blank is emitted, and only for http(s)). */
$cta_open = bb_theme_shell_normalize_cta( array( 'label' => 'x', 'url' => 'https://x.example', 'new_tab' => true ) );
check( 'new_tab -> target=_blank', ( $cta_open['target'] ?? '' ) === '_blank' );
check( 'new_tab -> rel hardened', ( $cta_open['rel'] ?? '' ) === 'noopener noreferrer' );

/* javascript: utility/social URLs dropped. */
check( 'unsafe utility link dropped', empty( bb_theme_shell_normalize_links( array( array( 'label' => 'x', 'url' => 'javascript:void(0)' ) ) ) ) );
check( 'unsafe social link dropped', empty( bb_theme_shell_normalize_social( array( array( 'label' => 'x', 'url' => 'vbscript:msgbox' ) ) ) ) );

/* Raw HTML in a label is escaped at output. */
add_filter( 'bb_theme_shell_header_data', static function ( $data ) {
    $data['cta'] = array( 'label' => '<script>alert(1)</script>', 'url' => 'https://ok.example/' );
    return $data;
}, 20, 1 );
set_theme_mod( 'bb_theme_shell_header', 'default' );
ob_start(); bb_theme_shell_render( 'header' ); $h = ob_get_clean();
check( 'provider label is escaped (no raw <script>)', false === strpos( $h, '<script>alert(1)</script>' ) );
check( 'provider label escaped as entities', false !== strpos( $h, '&lt;script&gt;' ) );
remove_all_filters( 'bb_theme_shell_header_data' );
bb_theme_shell_bridge_register();

/* Malformed provider payloads degrade safely (no fatal, no broken markup). */
add_filter( 'bb_theme_shell_header_data', static function ( $data ) {
    $data['cta'] = 'not-an-array';
    $data['utility_links'] = 'also-not-an-array';
    return $data;
}, 20, 1 );
set_theme_mod( 'bb_theme_shell_header', 'default' );
ob_start(); bb_theme_shell_render( 'header' ); $h = ob_get_clean();
check( 'malformed provider payload -> no CTA markup, no fatal', false === strpos( $h, 'bb-shell-cta' ) && false === stripos( $h, 'Fatal error' ) );
remove_all_filters( 'bb_theme_shell_header_data' );
bb_theme_shell_bridge_register();

/* =====================================================================
 * D. Merge / precedence: SiteSettings ownership preserved, non-destructive
 * ===================================================================== */

/* Provider supplies a CTA; the plugin bridge supplies contact/social; both
 * coexist without either overwriting the other. */
update_option( 'bb_site_settings', array_merge( $blank, array(
    'phone' => '+1 555 111', 'email' => 'a@b.com', 'facebook' => 'https://facebook.com/x',
) ) );
add_filter( 'bb_theme_shell_header_data', static function ( $data ) {
    $data['cta'] = array( 'label' => 'Go', 'url' => 'https://go.example' );
    return $data;
}, 20, 1 );
$header = bb_theme_shell_header_data();
check( 'merge: provider CTA + plugin contact coexist', ! empty( $header['cta'] ) && ! empty( $header['contact']['phone'] ) );

/* The bridge must NOT overwrite provider-supplied contact. */
$supplied = apply_filters( 'bb_theme_shell_footer_data', array( 'contact' => array( 'phone' => '+9 000' ) ) );
check( 'bridge does not overwrite provider contact', ( $supplied['contact']['phone'] ?? '' ) === '+9 000' );

/* Provider must not be able to hijack identity keys it does not own. */
$footer = bb_theme_shell_footer_data();
check( 'identity keys remain WordPress-owned', $footer['site_name'] === get_bloginfo( 'name' ) );

remove_all_filters( 'bb_theme_shell_header_data' );
bb_theme_shell_bridge_register();
update_option( 'bb_site_settings', $blank );

/* =====================================================================
 * E. Invariants: Theme domain-agnostic; resolver & design system intact
 * ===================================================================== */

$shell_src = file_get_contents( $theme_dir . '/inc/shell-data.php' );
$shell_code = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $shell_src );
$forbidden = array( 'LawFirm', 'bb_lawyer', 'bb_legal_service', 'LawFirmQueries', 'bb_site_settings', 'SiteSettings', 'bb_consultation', 'bb_appointment' );
$clean = true;
foreach ( $forbidden as $needle ) { if ( false !== strpos( $shell_code, $needle ) ) { $clean = false; } }
check( 'theme shell-data CODE has NO LawFirm/domain coupling', $clean );

/* Theme source as a whole must not reference the pack for shell rendering. */
$theme_hits = 0;
foreach ( glob( $theme_dir . '/inc/*.php' ) as $tf ) {
    $code = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $tf ) );
    foreach ( array( 'LawFirm', 'bb_lawyer', 'bb_consultation', 'bb_site_settings' ) as $needle ) {
        if ( false !== strpos( $code, $needle ) ) { $theme_hits++; }
    }
}
check( 'theme inc/ has no LawFirm/business coupling', 0 === $theme_hits );

/* Phase 15 resolver untouched. */
check( 'Phase 15 resolver intact', 'default' === bb_theme_shell_resolve( 'header', '../../etc' ) && 'centered' === bb_theme_shell_resolve( 'header', 'centered' ) );
/* Phase 14 design system intact. */
check( 'Phase 14 preset CSS intact', 0 === strpos( bb_theme_preset_css(), ':root{' ) );

bb_theme_reset_shell_variants();
restore_current_blog();

/* =====================================================================
 * F. Multisite isolation
 * ===================================================================== */
switch_to_blog( 2 );
set_theme_mod( 'bb_theme_shell_header', 'split' );
$s2 = bb_theme_shell_part_variant( 'header' );
restore_current_blog();
switch_to_blog( 1 );
$s1 = function_exists( 'bb_theme_shell_part_variant' ) ? bb_theme_shell_part_variant( 'header' ) : 'default';
restore_current_blog();
check( 'site 2 shell selection is split', 'split' === $s2 );
check( 'site 1 does not inherit site 2 selection', 'split' !== $s1 );

/* Cleanup. */
switch_to_blog( 2 );
update_option( 'bb_site_settings', $original_settings );
bb_theme_reset_shell_variants();
restore_current_blog();

echo 'DONE' . PHP_EOL;