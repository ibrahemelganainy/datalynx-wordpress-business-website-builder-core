<?php
/*
 * Phase 19 verification: SECOND BUSINESS PACK PROOF — Medical.
 *
 * Proves the platform thesis: a genuinely different business type can be added as
 * an INDEPENDENT pack without modifying the Core Theme and without introducing a
 * second design system.
 *
 * Sections:
 *   1. Core: pack self-registration, activation, generic rendering, no pack slugs
 *      in the Core renderer, unclaimed-section fallback.
 *   2. Medical: business type, pack, sections, components, variants, data contract.
 *   3. Design: Medical preset registration, resolution, user override, reset,
 *      token namespace, no second design system.
 *   4. LawFirm regression: pack still registers and boots; its own slugs intact.
 *   5. Theme: domain-agnostic source scan.
 *   6. Multisite: site isolation of business type / preset / business data.
 *
 * Run:  php tests/runtime-phase19-medical-pack.php
 */

define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

use BusinessBuilderCore\Core\PackManager;
use BusinessBuilderCore\Core\Container;
use BusinessBuilderCore\Settings\BusinessType;

$plugin_dir = WP_CONTENT_DIR . '/plugins/business-builder-core';
$theme_dir  = WP_CONTENT_DIR . '/themes/business-builder';

/* Load the canonical Theme's function layers (same set the Phase 15-18 suites use). */
if ( ! defined( 'BB_THEME_PATH' ) ) { define( 'BB_THEME_PATH', trailingslashit( $theme_dir ) ); }
if ( ! defined( 'BB_THEME_URL' ) ) { define( 'BB_THEME_URL', 'http://medical.builder.test/wp-content/themes/business-builder/' ); }
if ( ! defined( 'BB_THEME_VERSION' ) ) { define( 'BB_THEME_VERSION', '1.0.0' ); }
foreach ( array( 'setup', 'theme-support', 'enqueue', 'preset-resolver', 'shell-variants', 'shell-data', 'template-functions', 'template-hooks', 'navigation', 'design-schema', 'customization', 'components' ) as $f ) {
	$p = $theme_dir . '/inc/' . $f . '.php';
	if ( is_readable( $p ) ) { require_once $p; }
}

echo '=== 1. CORE ===' . PHP_EOL;

/* --- 1.1 The Core fires the pack self-registration hook. --- */
$sp_src = (string) file_get_contents( $plugin_dir . '/includes/Core/ServiceProvider.php' );
check( 'Core fires bb_register_packs', false !== strpos( $sp_src, "do_action( 'bb_register_packs'" ) );

/* --- 1.2 The Medical pack self-registers through it (no Core edit). --- */
$business_type = new BusinessType();
$pack_manager  = new PackManager( $business_type, new Container() );

$pack_manager->register( 'law_firm', 'BusinessBuilderCore\\Packs\\LawFirm\\LawFirmPack' );
do_action( 'bb_register_packs', $pack_manager );

check( 'LawFirm pack still registered (core behaviour unchanged)', $pack_manager->exists( 'law_firm' ) );
check( 'Medical pack self-registered via the hook', $pack_manager->exists( 'medical' ) );
check( 'Medical pack class resolved', 0 === strpos( (string) $pack_manager->get( 'medical' ), 'BusinessBuilderCore\\Packs\\Medical\\MedicalPack' ) );

/* --- 1.3 Core must NOT hardcode the Medical business type in its pack list. --- */
/* Strip comments first: the hook's own docblock uses the Medical slug as an example. */
$sp_code       = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $sp_src );
$core_registers = substr( $sp_code, 0, strpos( $sp_code, "do_action( 'bb_register_packs'" ) );
check( 'Core does NOT hardcode the medical pack registration', false === strpos( $core_registers, "'medical'" ) );
check( 'Core hardcodes ONLY its own law_firm pack', 1 === substr_count( $core_registers, 'pack_manager->register(' ) );

/* --- 1.4 The generic Core renderer contains no pack section slugs at all. --- */
$renderer_src = (string) file_get_contents( $plugin_dir . '/includes/Builder/SectionRenderer.php' );
$renderer_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $renderer_src );

$forbidden = array(
	"'lawyers'", "'legal_services'", "'practice_areas'", "'testimonials'", "'faq'",
	"'doctors'", "'medical_services'", "'departments'",
	'bb_lawyer', 'bb_doctor', 'bb_medical_service', 'LawFirm', 'Medical',
);
$leaks = array();
foreach ( $forbidden as $needle ) {
	if ( false !== strpos( $renderer_code, $needle ) ) { $leaks[] = $needle; }
}
check( 'Core SectionRenderer has NO pack section slugs (LawFirm or Medical)', array() === $leaks );
check( 'Core SectionRenderer offers the generic bb_render_section filter', false !== strpos( $renderer_code, "'bb_render_section'" ) );

/* --- 1.5 Unclaimed sections fall through to the generic renderer. --- */
$claimed = apply_filters( 'bb_render_section', false, 'a_pack_that_is_not_installed', array(), array(), array() );
check( 'an unclaimed section type falls through (no core hardcoding)', false === $claimed );

echo '=== 2. MEDICAL ===' . PHP_EOL;

/* --- 2.1 Business type is the EXISTING registry entry (no second system). --- */
check( 'business type `medical` declared in the existing BusinessType registry', $business_type->exists( 'medical' ) );
check( 'BusinessType remains the single source of truth (4+ types)', count( $business_type->get_all() ) >= 4 );

/* --- 2.2 The pack boots only for its own business type. --- */
$pm2 = new PackManager( new BusinessType(), new Container() );
do_action( 'bb_register_packs', $pm2 );
check( 'Medical pack boots for medical', true === $pm2->boot( 'medical' ) );
$medical_instance = $pm2->get_instance( 'medical' );
check( 'booted instance is a MedicalPack', $medical_instance instanceof \BusinessBuilderCore\Packs\Medical\MedicalPack );
check( 'boot is idempotent (no duplicate instance)', $medical_instance === $pm2->get_instance( 'medical' ) );
check( 'booting an unregistered pack still returns false', false === $pm2->boot( 'no_such_pack' ) );

/* --- 2.3 Sections registered in the ONE shared registry, in their own category. --- */
$registry = new \BusinessBuilderCore\Builder\SectionRegistry();
$sections = new \BusinessBuilderCore\Packs\Medical\Sections\MedicalSections( $registry );
$sections->register();

check( 'doctors section registered', $registry->exists( 'doctors' ) );
check( 'medical_services section registered', $registry->exists( 'medical_services' ) );
check( 'Medical sections use the medical category', array( 'doctors', 'medical_services' ) === array_values(
	array_filter( array_keys( $registry->get_all() ), function ( $s ) use ( $registry ) {
		return 'medical' === $registry->get( $s )['category'];
	} )
) );
check( 'Medical sections expose typed settings + content schemas',
	! empty( $registry->get_settings_schema( 'doctors' ) ) && ! empty( $registry->get_content_schema( 'doctors' ) ) );

/* --- 2.4 The pack CLAIMS its own types via the generic hook. --- */
check( 'pack claims doctors through bb_render_section', true === apply_filters( 'bb_render_section', false, 'doctors', array(), array(), array() ) );
check( 'pack claims medical_services through bb_render_section', true === apply_filters( 'bb_render_section', false, 'medical_services', array(), array(), array() ) );
check( 'pack does NOT claim foreign types (consultation belongs to LawFirm)', false === apply_filters( 'bb_render_section', false, 'consultation', array(), array(), array() ) );

/* --- 2.5 Domain entities + taxonomy are pack-owned. --- */
check( 'bb_doctor post type declared by the pack class', class_exists( 'BusinessBuilderCore\\Packs\\Medical\\PostTypes\\Doctor' ) );
check( 'bb_medical_service post type declared by the pack class', class_exists( 'BusinessBuilderCore\\Packs\\Medical\\PostTypes\\MedicalService' ) );
check( 'bb_specialty taxonomy declared by the pack class', class_exists( 'BusinessBuilderCore\\Packs\\Medical\\Taxonomies\\Specialty' ) );
check( 'pack entity slugs use the pack bb_ namespace',
	'bb_doctor' === \BusinessBuilderCore\Packs\Medical\PostTypes\Doctor::POST_TYPE
	&& 'bb_medical_service' === \BusinessBuilderCore\Packs\Medical\PostTypes\MedicalService::POST_TYPE
	&& 'bb_specialty' === \BusinessBuilderCore\Packs\Medical\Taxonomies\Specialty::TAXONOMY );

/* --- 2.6 Components + variants ship from the pack via bb_component_roots. --- */
/*
 * The root is registered by the PACK at register() time, so assert it through a
 * booted pack (the same path a real request takes) rather than an empty filter.
 */
$pm_boot = new PackManager( new BusinessType(), new Container() );
do_action( 'bb_register_packs', $pm_boot );
$pm_boot->boot( 'medical' );

$roots = apply_filters( 'bb_component_roots', array( $theme_dir . '/templates/components' ) );
$medical_root = $plugin_dir . '/packs/Medical/Sections/components';
/* Normalise separators: the registered path and the expected path must agree. */
$roots_norm = array_map( function ( $r ) { return str_replace( '\\', '/', (string) $r ); }, $roots );
check( 'pack registers its component root via bb_component_roots (when booted)', in_array( str_replace( '\\', '/', $medical_root ), $roots_norm, true ) );

$doc_dir = $medical_root . '/doctor';
check( 'doctor/card component template ships in the pack', is_readable( $doc_dir . '/card.php' ) );
check( 'doctor/card compact variant template ships', is_readable( $doc_dir . '/card-compact.php' ) );
check( 'doctor/card horizontal variant template ships', is_readable( $doc_dir . '/card-horizontal.php' ) );
check( 'medical-service/card component template ships', is_readable( $medical_root . '/medical-service/card.php' ) );
check( 'medical-service/card featured variant template ships', is_readable( $medical_root . '/medical-service/card-featured.php' ) );

/* --- 2.7 Section layout variants registered in the shared SectionVariants registry. --- */
$variants = bb_section_variants();
$doctor_layouts = $variants->available( 'doctors' );
sort( $doctor_layouts );
check( 'doctors exposes default/grid/list layouts', array( 'default', 'grid', 'list' ) === $doctor_layouts );
check( 'medical_services exposes default/featured layouts', array( 'default', 'featured' ) === $variants->available( 'medical_services' ) );

/* Traversal safety is inherited from the core resolver, not re-implemented. */
check( 'traversal-unsafe variant request resolves to default', 'default' === bb_resolve_section_variant( 'doctors', '../../etc/passwd' ) );
check( 'unknown variant resolves to default', 'default' === bb_resolve_section_variant( 'doctors', 'not_a_layout' ) );

/* --- 2.8 Prepared-data contract: no variant template may query. --- */
$variant_dir = $plugin_dir . '/packs/Medical/Sections/variants';
$query_hits  = 0;
foreach ( array( 'doctors/default.php', 'doctors/grid.php', 'doctors/list.php', 'medical-services/default.php', 'medical-services/featured.php' ) as $rel ) {
	$src = (string) file_get_contents( $variant_dir . '/' . $rel );
	foreach ( array( 'WP_Query', 'get_posts(', 'get_terms(', 'get_post_meta(', "new MedicalQueries", 'wp_query' ) as $needle ) {
		if ( false !== strpos( $src, $needle ) ) { $query_hits++; }
	}
}
check( 'no Medical variant template performs a business query', 0 === $query_hits );

/* --- 2.9 Component templates are presentation-only and pack-agnostic of builder. --- */
$comp_queries = 0;
foreach ( glob( $medical_root . '/*/*.php' ) as $file ) {
	$src = (string) file_get_contents( $file );
	foreach ( array( 'WP_Query', 'get_posts(', 'get_terms(', 'get_post_meta(', 'SectionRenderer', 'PageManager' ) as $needle ) {
		if ( false !== strpos( $src, $needle ) ) { $comp_queries++; }
	}
}
check( 'no Medical component queries data or knows the Page Builder', 0 === $comp_queries );

/* --- 2.10 Pack templates use only the Theme token namespace (--bb-*). --- */
$token_hits = 0;
foreach ( glob( $medical_root . '/*/*.php' ) as $file ) {
	$src = (string) file_get_contents( $file );
	if ( preg_match( '/--medical-|--med-/', $src ) ) { $token_hits++; }
}
check( 'Medical components introduce NO second token namespace', 0 === $token_hits );

echo '=== 3. DESIGN ===' . PHP_EOL;

/* --- 3.1 The Medical preset is contributed through the EXISTING theme filters. --- */
$presets = apply_filters( 'bb_theme_presets', array( 'default' => array(), 'modern' => array(), 'luxury' => array() ) );
check( 'Medical preset registered in the theme preset registry', isset( $presets['medical-modern'] ) );
check( 'Medical preset carries a label', isset( $presets['medical-modern']['label'] ) );
check( 'Theme built-in presets remain present (default fallback intact)', isset( $presets['default'], $presets['modern'], $presets['luxury'] ) );

/* --- 3.2 The preset uses ONLY existing --bb-* tokens. --- */
$preset_tokens = \BusinessBuilderCore\Packs\Medical\Design\MedicalPresets::class;
$preset_obj    = new $preset_tokens();
$foreign = 0;
foreach ( array_keys( $preset_obj->tokens() ) as $token ) {
	if ( 0 !== strpos( $token, '--bb-' ) ) { $foreign++; }
}
check( 'every Medical preset token is in the --bb-* namespace', 0 === $foreign );
check( 'Medical preset declares no --medical-* token', 0 === count( array_filter( array_keys( $preset_obj->tokens() ), function ( $t ) { return 0 === strpos( $t, '--medical' ); } ) ) );

/* --- 3.3 The preset resolver still validates and falls back safely. --- */
check( 'theme preset resolver exists (single design system)',
	function_exists( 'bb_theme_get_preset' ) && function_exists( 'bb_theme_preset_config' ) && function_exists( 'bb_theme_preset_css' ) );
$unknown_preset = bb_theme_get_preset();
check( 'preset resolver returns a valid preset slug', is_string( $unknown_preset ) && '' !== $unknown_preset );

/* --- 3.4 There is exactly ONE design system: the theme's. --- */
$theme_inc = glob( $theme_dir . '/inc/*.php' );
$second_design_system = 0;
foreach ( $theme_inc as $file ) {
	$src = (string) file_get_contents( $file );
	/* No pack/domain knowledge may appear in the theme's design layer. */
	foreach ( array( 'Medical', 'bb_doctor', 'medical-modern', 'bb_medical_service' ) as $needle ) {
		if ( false !== strpos( $src, $needle ) ) { $second_design_system++; }
	}
}
check( 'Theme design layer contains NO Medical references (no Theme→Pack dependency)', 0 === $second_design_system );

/* --- 3.5 The Medical site actually resolves the Medical preset, and a user
 *         override WINS over it (the Phase 19 §16 critical test). --- */
$medical_blog = (int) get_option( 'bb_phase19_medical_blog_id', 0 );

if ( $medical_blog > 0 ) {

	switch_to_blog( $medical_blog );

	check( 'Medical site selected the Medical preset', 'medical-modern' === (string) get_theme_mod( 'bb_theme_preset', '' ) );

	$resolved = bb_theme_preset_config();

	check( 'resolved config carries the preset primary token', isset( $resolved['--bb-color-primary'] ) );
	check( 'resolved primary equals the preset value when no override is set',
		'#0f766e' === strtolower( (string) $resolved['--bb-color-primary'] ) );

	/* User override must beat the preset. */
	$saved_mod = get_theme_mod( 'bb_design_color_primary', '' );
	set_theme_mod( 'bb_design_color_primary', '#b91c1c' );

	$overridden = bb_theme_preset_config();
	check( 'user customization OVERRIDES the Medical preset',
		'#b91c1c' === strtolower( (string) $overridden['--bb-color-primary'] ) );
	check( 'non-overridden preset tokens survive the override',
		isset( $overridden['--bb-radius-md'] ) && '0.75rem' === (string) $overridden['--bb-radius-md'] );

	/* Reset restores preset inheritance. */
	remove_theme_mod( 'bb_design_color_primary' );
	$restored = bb_theme_preset_config();
	check( 'reset restores preset inheritance',
		'#0f766e' === strtolower( (string) $restored['--bb-color-primary'] ) );

	if ( '' !== $saved_mod ) { set_theme_mod( 'bb_design_color_primary', $saved_mod ); }

	restore_current_blog();

} else {
	check( 'Medical test site available for design assertions', false );
}

echo '=== 4. LAWFIRM REGRESSION ===' . PHP_EOL;

/* --- 4.1 LawFirm still registers and boots unchanged. --- */
/*
 * Two distinct assertions, because they test different things:
 *   (a) bb_register_packs only ever contributes packs OTHER than law_firm —
 *       law_firm is owned by the Core, so an isolated PackManager + the hook
 *       legitimately contains Medical only;
 *   (b) the Core itself still registers law_firm (ServiceProvider).
 */
$pm3 = new PackManager( new BusinessType(), new Container() );
do_action( 'bb_register_packs', $pm3 );
check( 'bb_register_packs contributes packs (medical) without touching law_firm', $pm3->exists( 'medical' ) && ! $pm3->exists( 'law_firm' ) );

/* The Core's own registration is the source of truth for law_firm. */
check( 'Core still registers the law_firm pack', false !== strpos( $sp_code, "'law_firm'" ) );
check( 'Core still maps law_firm to LawFirmPack', false !== strpos( $sp_code, 'LawFirmPack' ) );

/* And the real pack manager (Core-registered) still boots LawFirm. */
$pm_real = new PackManager( new BusinessType(), new Container() );
$pm_real->register( 'law_firm', 'BusinessBuilderCore\\Packs\\LawFirm\\LawFirmPack' );
do_action( 'bb_register_packs', $pm_real );
check( 'LawFirm pack registers in the real (Core-shaped) manager', $pm_real->exists( 'law_firm' ) );
check( 'LawFirm pack boots', true === $pm_real->boot( 'law_firm' ) );
check( 'both packs coexist in one manager', $pm_real->exists( 'law_firm' ) && $pm_real->exists( 'medical' ) );

/* --- 4.2 LawFirm's own section slugs are untouched and still claimed by LawFirm. --- */
$lawfirm_src = (string) file_get_contents( $plugin_dir . '/packs/LawFirm/Sections/LawFirmSections.php' );
check( 'LawFirm still claims lawyers', false !== strpos( $lawfirm_src, "'lawyers'" ) );
check( 'LawFirm still claims legal_services', false !== strpos( $lawfirm_src, "'legal_services'" ) );
check( 'LawFirm still claims practice_areas', false !== strpos( $lawfirm_src, "'practice_areas'" ) );
check( 'LawFirm still claims testimonials', false !== strpos( $lawfirm_src, "'testimonials'" ) );
check( 'LawFirm still claims faq', false !== strpos( $lawfirm_src, "'faq'" ) );
check( 'LawFirm uses the generic bb_render_section hook', false !== strpos( $lawfirm_src, "'bb_render_section'" ) );

/* --- 4.3 LawFirm sites still have their business type and preset. --- */
$lawfirm_blog = 0;
foreach ( get_sites( array( 'number' => 300 ) ) as $site ) {
	if ( 'lawfirm.builder.test' === $site->domain ) { $lawfirm_blog = (int) $site->blog_id; break; }
}

if ( $lawfirm_blog > 0 ) {
	switch_to_blog( $lawfirm_blog );
	check( 'LawFirm site business type unchanged', 'law_firm' === (string) get_option( 'bb_business_type' ) );
	check( 'LawFirm site theme unchanged', 'business-builder' === (string) get_option( 'stylesheet' ) );
	check( 'LawFirm site did NOT inherit the Medical preset', 'medical-modern' !== (string) get_theme_mod( 'bb_theme_preset', '' ) );
	restore_current_blog();
} else {
	check( 'LawFirm site reachable for regression assertions', false );
}

echo '=== 5. THEME DOMAIN-AGNOSTICISM ===' . PHP_EOL;

$hits     = 0;
$offenders = array();
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme_dir ) );
foreach ( $rii as $file ) {
	if ( $file->isDir() || 'php' !== strtolower( $file->getExtension() ) ) { continue; }
	$path = $file->getPathname();
	if ( false !== strpos( $path, '_phase1' ) ) { continue; }
	$code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $path ) );
	foreach ( array( 'LawFirm', 'Medical', 'bb_lawyer', 'bb_doctor', 'bb_legal_service', 'bb_medical_service', 'bb_practice_area', 'bb_specialty', 'bb_business_type', 'Packs\\' ) as $needle ) {
		if ( false !== strpos( $code, $needle ) ) { $hits++; $offenders[] = basename( $path ) . ':' . $needle; }
	}
}
check( 'Theme source has NO pack/business coupling (LawFirm or Medical)', 0 === $hits );
if ( $hits > 0 ) { echo '   offenders: ' . implode( ', ', array_slice( $offenders, 0, 10 ) ) . PHP_EOL; }

echo '=== 6. MULTISITE ISOLATION ===' . PHP_EOL;

if ( $medical_blog > 0 && $lawfirm_blog > 0 ) {

	/* Business type isolation. */
	switch_to_blog( $medical_blog );
	$med_type = (string) get_option( 'bb_business_type' );
	$med_preset = (string) get_theme_mod( 'bb_theme_preset', '' );
	restore_current_blog();

	switch_to_blog( $lawfirm_blog );
	$lf_type = (string) get_option( 'bb_business_type' );
	$lf_preset = (string) get_theme_mod( 'bb_theme_preset', '' );
	restore_current_blog();

	check( 'Medical site business type is medical', 'medical' === $med_type );
	check( 'LawFirm site business type is law_firm', 'law_firm' === $lf_type );
	check( 'business types do not leak across sites', $med_type !== $lf_type );
	check( 'design presets do not leak across sites', $med_preset !== $lf_preset || '' === $lf_preset );

	/* Business DATA isolation: Medical owns doctors, LawFirm must have none. */
	/*
	 * Count by querying posts directly rather than wp_count_posts(): the pack's
	 * CPTs are only registered while the Medical pack is booted, and this suite
	 * runs its own isolated pack lifecycle.
	 */
	switch_to_blog( $medical_blog );
	$med_doctors = count( get_posts( array( 'post_type' => 'bb_doctor', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	$med_lawyers = count( get_posts( array( 'post_type' => 'bb_lawyer', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	restore_current_blog();

	switch_to_blog( $lawfirm_blog );
	$lf_doctors = count( get_posts( array( 'post_type' => 'bb_doctor', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	$lf_lawyers = count( get_posts( array( 'post_type' => 'bb_lawyer', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	restore_current_blog();

	check( 'Medical site has doctors', $med_doctors > 0 );
	check( 'Medical site has NO lawyers (LawFirm data did not leak in)', 0 === $med_lawyers );
	check( 'LawFirm site has NO doctors (Medical data did not leak in)', 0 === $lf_doctors );

	/* Site 1 must be untouched by all of this. */
	switch_to_blog( 1 );
	$s1_type = (string) get_option( 'bb_business_type' );
	$s1_doctors = count( get_posts( array( 'post_type' => 'bb_doctor', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	restore_current_blog();

	check( 'site 1 has no BB business type', '' === $s1_type );
	check( 'site 1 has no Medical data', 0 === $s1_doctors );
}

echo 'DONE' . PHP_EOL;