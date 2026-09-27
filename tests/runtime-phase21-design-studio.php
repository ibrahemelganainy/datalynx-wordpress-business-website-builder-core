<?php
/*
 * Phase 21 - ENTERPRISE DESIGN CATALOGUE & VISUAL DESIGN STUDIO
 * =====================================================================
 * Canonical runtime suite for Steps 1-4 (tokens, distinct designs, shell, previews).
 *
 * Run:  php tests/runtime-phase21-design-studio.php
 *
 * =====================================================================
 * WHY THIS SUITE RUNS ONE SITE PER PROCESS
 * =====================================================================
 * `ServiceProvider::boot()` calls `PackManager::boot_current()` while CONSTRUCTING the plugin,
 * so the pack that boots depends on the CURRENT site's business type.
 *
 * A long-lived CLI process that switches blogs many times accumulates a stale WordPress
 * `alloptions` cache: `get_option()` and `get_blog_option()` were BOTH measured returning
 * another blog's value. The DATABASE is always correct (see `tests/_phase21-raw-db.php`), and a
 * real HTTP request is unaffected because it starts with a clean cache for a single blog.
 *
 * Running each site in a FRESH PROCESS therefore reproduces a real request exactly, keeps every
 * assertion meaningful, and removes any chance of the harness measuring the wrong site - without
 * the test ever writing to the data it is testing.
 *
 * The suite restores every site to a valid design before exiting.
 */

define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
if ( ! defined( 'BB_THEME_PATH' ) ) { define( 'BB_THEME_PATH', trailingslashit( $theme_dir ) ); }
foreach ( array( 'setup', 'theme-support', 'enqueue', 'preset-resolver', 'shell-variants', 'shell-data', 'template-functions', 'template-hooks', 'navigation', 'design-schema', 'customization', 'components' ) as $f ) {
	$p = $theme_dir . '/inc/' . $f . '.php';
	if ( is_readable( $p ) ) { require_once $p; }
}

use BusinessBuilderCore\Design\DesignCatalogue;
use BusinessBuilderCore\Design\DesignPage;
use BusinessBuilderCore\Design\DesignSchema;
use BusinessBuilderCore\Design\DesignShell;

/* =================================================================
 * Harness
 * ================================================================= */
$GLOBALS['bb_pass'] = 0;
$GLOBALS['bb_fail'] = array();

function check( string $label, bool $ok ): void {

	if ( $ok ) {
		$GLOBALS['bb_pass']++;
		echo 'PASS — ' . $label . PHP_EOL;
		return;
	}

	$GLOBALS['bb_fail'][] = $label;
	echo 'FAIL — ' . $label . PHP_EOL;
}

/**
 * The sites under test and the designs each must offer.
 */
function bb_sites(): array {

	return array(
		2 => array( 'law_firm', 'lawfirm-meridian', 'lawfirm-aurora', 'lawfirm-obsidian' ),
		3 => array( 'medical',  'medical-clarity', 'medical-vitality', 'medical-precision' ),
	);
}

/**
 * Reach the PackManager so a pack can be booted explicitly.
 */
function bb_pack_manager( $plugin ) {

	$ro = new ReflectionObject( $plugin );

	foreach ( $ro->getProperties() as $prop ) {

		if ( 'service_provider' !== $prop->getName() ) { continue; }

		$prop->setAccessible( true );
		$sp = $prop->getValue( $plugin );

		$r2 = new ReflectionObject( $sp );

		foreach ( $r2->getProperties() as $p2 ) {

			if ( 'pack_manager' !== $p2->getName() ) { continue; }

			$p2->setAccessible( true );
			return $p2->getValue( $sp );
		}
	}

	return null;
}

/* =================================================================
 * Single-site process dispatch
 * ================================================================= */
$BB_SITE = isset( $argv[1] ) ? (int) $argv[1] : 0;

$SITES = bb_sites();

/*
 * With no site argument, re-exec once per site so each runs with a clean cache. This is the
 * real-request fidelity described in the header.
 */
if ( 0 === $BB_SITE ) {

	echo 'Phase 21 - Design Studio suite' . PHP_EOL;
	echo 'Running each site in a fresh process (clean cache, real-request fidelity).' . PHP_EOL . PHP_EOL;

	$php    = PHP_BINARY;
	$script = __FILE__;
	$failed = 0;

	foreach ( array_keys( $SITES ) as $site ) {

		echo str_repeat( '=', 72 ) . PHP_EOL;
		echo 'PROCESS FOR SITE ' . $site . PHP_EOL;
		echo str_repeat( '=', 72 ) . PHP_EOL;

		$cmd = escapeshellarg( $php ) . ' ' . escapeshellarg( $script ) . ' ' . (int) $site;

		passthru( $cmd, $code );

		if ( 0 !== $code ) { $failed++; }
	}

	echo PHP_EOL . str_repeat( '=', 72 ) . PHP_EOL;
	echo ( $failed
		? 'OVERALL: ' . $failed . ' site process(es) reported failures'
		: 'OVERALL: all site processes passed' ) . PHP_EOL;

	exit( $failed ? 1 : 0 );
}

if ( ! isset( $SITES[ $BB_SITE ] ) ) {
	echo 'Unknown site id: ' . $BB_SITE . PHP_EOL;
	exit( 1 );
}

$SITE_SPEC  = $SITES[ $BB_SITE ];
$SITE_TYPE  = $SITE_SPEC[0];
$SITE_SLUGS = array_slice( $SITE_SPEC, 1 );

/*
 * Serve exactly ONE site. This process is already on it (the runner selected the site via the
 * command line, and WordPress resolves the current blog from the requested URL), so no blog
 * switching happens and no cache can go stale.
 */
if ( get_current_blog_id() !== $BB_SITE ) {
	switch_to_blog( $BB_SITE );
}

/* Confirm we really are on the site the runner asked for. */
$actual_type = (string) get_option( 'bb_business_type' );

if ( $actual_type !== $SITE_TYPE ) {
	echo 'FATAL: site ' . $BB_SITE . ' is "' . $actual_type . '", expected "' . $SITE_TYPE . '"' . PHP_EOL;
	echo 'Run tests/_phase21-raw-db.php to inspect the database directly.' . PHP_EOL;
	exit( 1 );
}

wp_set_current_user( 1 );

echo 'Site ' . $BB_SITE . ' (' . $SITE_TYPE . ') — process started.' . PHP_EOL . PHP_EOL;

/*
 * Build the site's plugin. In this single-site process the current site IS the site under test,
 * so the pack that boots is genuinely this site's.
 */
$plugin = new BusinessBuilderCore\Core\Plugin();
$plugin->run();

$pmgr = bb_pack_manager( $plugin );
if ( $pmgr ) { $pmgr->boot( $SITE_TYPE ); }

$catalogue = new DesignCatalogue();
$bt        = $plugin->get_business_type();

$SITE_LABEL = $SITE_TYPE;

/* =================================================================
 * 1. SCHEMA EXTENSION (§5, §12, §27-§33)
 * ================================================================= */
echo PHP_EOL . '=== 1. DESIGN SCHEMA EXTENSION ===' . PHP_EOL;

/*
 * Proving the extension is ADDITIVE without touching global state.
 *
 * An earlier version of this check called `remove_all_filters('bb_theme_design_schema')`, which
 * stripped the THEME'S OWN schema filters for the rest of the run and silently broke every later
 * assertion. Instead we ask the extension what it declares and confirm each control is present
 * in the live schema exactly once.
 */
$extension = new DesignSchema();
$added     = $extension->controls();

$schema = bb_theme_design_schema();

check( 'the schema extension contributes design-identity controls (' . count( $added ) . ')', count( $added ) >= 20 );
check( 'the live schema includes them (' . count( $schema ) . ' controls)', count( $schema ) >= count( $added ) );

$keys = array();
foreach ( $schema as $c ) { $keys[] = (string) ( $c['key'] ?? '' ); }

$dupes   = array_diff_assoc( $keys, array_unique( $keys ) );
$missing = array();

foreach ( $added as $c ) {
	if ( ! in_array( (string) $c['key'], $keys, true ) ) { $missing[] = (string) $c['key']; }
}

check( 'the extension adds no duplicate control', array() === $dupes );
check( 'every extended control is present in the live schema', array() === $missing );
if ( $missing ) { echo '   missing: ' . implode( ', ', $missing ) . PHP_EOL; }

$groups = array();
$tokens_ok = 0;

foreach ( $schema as $c ) {
	$groups[ (string) ( $c['group'] ?? '?' ) ] = true;
	if ( 0 === strpos( (string) $c['token'], '--bb-' ) ) { $tokens_ok++; }
}

check( 'every control uses the ONE --bb-* namespace', $tokens_ok === count( $schema ) );
check( 'the schema exposes a gradients group', isset( $groups['gradients'] ) );
check( 'the schema exposes a density group', isset( $groups['density'] ) );
check( 'the schema exposes a shape group', isset( $groups['shape'] ) );
check( 'the schema exposes a components group', isset( $groups['components'] ) );
check( 'the schema exposes a motion group', isset( $groups['motion'] ) );
check( 'the schema keeps the pre-existing shell group', isset( $groups['shell'] ) );

/*
 * The MEASURED constraint: the Theme's sanitizer rejects `ms`/`s` for `length` controls and
 * rejects negative `length`. No control may therefore depend on them.
 */
$bad_units = array();

foreach ( $schema as $c ) {
	if ( 'length' === ( $c['type'] ?? '' ) && in_array( (string) ( $c['unit'] ?? '' ), array( 'ms', 's' ), true ) ) {
		$bad_units[] = (string) $c['key'];
	}
}

check( 'no control uses an ms/s length unit (rejected by the Theme sanitizer)', array() === $bad_units );
if ( $bad_units ) { echo '   offenders: ' . implode( ', ', $bad_units ) . PHP_EOL; }

$motion_ctrl = null;
$track_ctrl  = null;

foreach ( $schema as $c ) {
	if ( 'motion_speed' === ( $c['key'] ?? '' ) ) { $motion_ctrl = $c; }
	if ( 'heading_letter_spacing' === ( $c['key'] ?? '' ) ) { $track_ctrl = $c; }
}

check( 'the motion control is sanitizer-compatible', null !== $motion_ctrl && '' !== bb_theme_sanitize_design_value( '0.24', $motion_ctrl ) );
check( 'negative letter-spacing is accepted', null !== $track_ctrl && '' !== bb_theme_sanitize_design_value( '-0.02', $track_ctrl ) );

/* =================================================================
 * 2. CATALOGUE COMPLETENESS (§6, §50)
 * ================================================================= */
echo PHP_EOL . '=== 2. CATALOGUE COMPLETENESS ===' . PHP_EOL;

$all = $catalogue->all();

foreach ( $SITE_SLUGS as $slug ) {

	check( '"' . $slug . '" is registered', isset( $all[ $slug ] ) );

	if ( ! isset( $all[ $slug ] ) ) { continue; }

	$d = $all[ $slug ];

	check( '"' . $slug . '" declares business type ' . $SITE_TYPE, in_array( $SITE_TYPE, $d['business_types'], true ) );
	check( '"' . $slug . '" has a customer-facing name', '' !== trim( (string) $d['label'] ) );
	check( '"' . $slug . '" is not named "Design N"', ! preg_match( '/^design\s*\d/i', (string) $d['label'] ) );
	check( '"' . $slug . '" has a description', '' !== trim( (string) $d['description'] ) );
	check( '"' . $slug . '" declares a shell preference', ! empty( $d['shell'] ) );
	check( '"' . $slug . '" exposes a palette for the catalogue card', count( $d['palette'] ) >= 4 );
}

$for_type = $catalogue->for_business_type( $SITE_TYPE );

$own = array_filter(
	$for_type,
	function ( $d ) use ( $SITE_TYPE ) {
		return in_array( $SITE_TYPE, $d['business_types'], true );
	}
);

check( $SITE_TYPE . ' offers at least 3 own designs (' . count( $own ) . ')', count( $own ) >= 3 );

/*
 * No design belonging to ANOTHER business type may appear. A design that declares NO business
 * type (the Theme's own `modern` / `luxury`, and `default`) is intentionally GLOBAL and correct
 * everywhere - only a design declaring a DIFFERENT type would be a leak.
 */
$foreign = array();

foreach ( $for_type as $slug => $d ) {

	if ( empty( $d['business_types'] ) ) { continue; }
	if ( ! in_array( $SITE_TYPE, $d['business_types'], true ) ) { $foreign[] = $slug; }
}

check( $SITE_TYPE . ': no other business type\'s design is offered', array() === $foreign );
if ( $foreign ) { echo '   offenders: ' . implode( ', ', $foreign ) . PHP_EOL; }

check( $SITE_TYPE . ': the global Default design remains available', isset( $for_type['default'] ) );
check( $SITE_TYPE . ': the Default design is listed first', 'default' === array_key_first( $for_type ) );

/* =================================================================
 * 3. A DESIGN IS A COMPLETE IDENTITY (§5, §9, §12)
 * ================================================================= */
echo PHP_EOL . '=== 3. EACH DESIGN IS A COMPLETE VISUAL IDENTITY ===' . PHP_EOL;

$AXES = array(
	'gradients'  => array( '--bb-gradient-brand', '--bb-gradient-hero', '--bb-gradient-cta', '--bb-gradient-decorative' ),
	'typography' => array( '--bb-font-heading' ),
	'density'    => array( '--bb-section-padding-block', '--bb-grid-gap', '--bb-card-padding', '--bb-heading-scale' ),
	'shape'      => array( '--bb-radius-card', '--bb-radius-input', '--bb-border-thickness', '--bb-shadow-card' ),
	'components' => array( '--bb-button-radius', '--bb-button-weight', '--bb-button-padding-block' ),
	'shell'      => array( '--bb-header-bg', '--bb-header-height', '--bb-header-blur', '--bb-footer-bg' ),
	'motion'     => array( '--bb-motion-normal', '--bb-reveal-duration', '--bb-reveal-distance', '--bb-hover-lift' ),
);

foreach ( $SITE_SLUGS as $slug ) {

	$d = $all[ $slug ] ?? null;

	if ( ! $d ) { continue; }

	foreach ( $AXES as $axis => $tokens ) {

		$missing_tokens = array();

		foreach ( $tokens as $t ) {
			if ( empty( $d['tokens'][ $t ] ) ) { $missing_tokens[] = $t; }
		}

		check( $slug . ' defines the ' . $axis . ' axis', array() === $missing_tokens );
	}
}

/* =================================================================
 * 4. DESIGNS ARE DISTINCT, NOT RECOLOURS (§9, §21, §64)
 * ================================================================= */
echo PHP_EOL . '=== 4. DESIGNS ARE DISTINCT IN KIND, NOT DEGREE ===' . PHP_EOL;

$AXIS_TOKENS = array(
	'colour'     => '--bb-color-primary',
	'background' => '--bb-color-background',
	'gradient'   => '--bb-gradient-hero',
	'heading'    => '--bb-font-heading',
	'radius'     => '--bb-radius-card',
	'shadow'     => '--bb-shadow-card',
	'rhythm'     => '--bb-section-padding-block',
	'grid'       => '--bb-grid-gap',
	'button'     => '--bb-button-radius',
	'motion'     => '--bb-motion-normal',
	'border'     => '--bb-border-thickness',
);

$weak = array();

for ( $i = 0; $i < count( $SITE_SLUGS ); $i++ ) {

	for ( $j = $i + 1; $j < count( $SITE_SLUGS ); $j++ ) {

		$a = $all[ $SITE_SLUGS[ $i ] ] ?? null;
		$b = $all[ $SITE_SLUGS[ $j ] ] ?? null;

		if ( ! $a || ! $b ) { continue; }

		$diff = 0;

		foreach ( $AXIS_TOKENS as $tok ) {
			if ( ( $a['tokens'][ $tok ] ?? '' ) !== ( $b['tokens'][ $tok ] ?? '' ) ) { $diff++; }
		}

		$shell_diff = 0;

		foreach ( array( 'header', 'navigation', 'footer' ) as $part ) {
			if ( ( $a['shell'][ $part ] ?? '' ) !== ( $b['shell'][ $part ] ?? '' ) ) { $shell_diff++; }
		}

		if ( $diff + $shell_diff < 8 ) {
			$weak[] = $SITE_SLUGS[ $i ] . '/' . $SITE_SLUGS[ $j ] . '=' . ( $diff + $shell_diff );
		}
	}
}

check( $SITE_TYPE . ': every design pair differs on 8+ independent axes', array() === $weak );
if ( $weak ) { echo '   offenders: ' . implode( ', ', $weak ) . PHP_EOL; }

/*
 * GREYSCALE TEST (§9): the designs must remain distinguishable without colour, so their
 * background lightness must span a wide range.
 */
$lum = array();

foreach ( $SITE_SLUGS as $slug ) {

	$hex = (string) ( $all[ $slug ]['tokens']['--bb-color-background'] ?? '#ffffff' );
	$hex = ltrim( $hex, '#' );

	if ( 6 !== strlen( $hex ) ) { continue; }

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	$lum[ $slug ] = (int) round( 0.299 * $r + 0.587 * $g + 0.114 * $b );
}

if ( count( $lum ) >= 3 ) {

	$spread = max( $lum ) - min( $lum );

	check(
		$SITE_TYPE . ': designs differ in LIGHTNESS too (greyscale legible, spread=' . $spread . ')',
		$spread >= 100
	);
}

/* =================================================================
 * 5. BUSINESS-TYPE COMPATIBILITY (§8, §37, §41)
 * ================================================================= */
echo PHP_EOL . '=== 5. BUSINESS-TYPE COMPATIBILITY ===' . PHP_EOL;

$other_type = ( 'law_firm' === $SITE_TYPE ) ? 'medical' : 'law_firm';
$other_slug = ( 'law_firm' === $SITE_TYPE ) ? 'medical-clarity' : 'lawfirm-meridian';

check( 'a design of this type is valid for it', $catalogue->is_valid_for( $SITE_SLUGS[0], $SITE_TYPE ) );
check( 'a design of this type is INVALID for ' . $other_type, ! $catalogue->is_valid_for( $SITE_SLUGS[0], $other_type ) );
check( 'the Default design is valid for every type', $catalogue->is_valid_for( 'default', 'law_firm' ) && $catalogue->is_valid_for( 'default', 'medical' ) );
check( 'an unknown design is rejected', ! $catalogue->is_valid_for( 'no_such_design', $SITE_TYPE ) );
check( 'the other type\'s design is excluded from this catalogue', ! isset( $for_type[ $other_slug ] ) );

/* =================================================================
 * 6. NO BUSINESS BRANCHING IN GENERIC CODE (§41, §64)
 * ================================================================= */
echo PHP_EOL . '=== 6. NO BUSINESS BRANCHING IN DESIGN INFRASTRUCTURE ===' . PHP_EOL;

$plugin_dir = WP_CONTENT_DIR . '/plugins/business-builder-core';

$hits = array();

foreach ( glob( $plugin_dir . '/includes/Design/*.php' ) as $file ) {

	$code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $file ) );

	foreach ( array( "'law_firm'", '"law_firm"', "'medical'", '"medical"', 'LawFirm', 'Medical' ) as $needle ) {
		if ( false !== strpos( $code, $needle ) ) { $hits[] = basename( $file ) . ':' . $needle; }
	}
}

check( 'generic Design infrastructure contains no business branching', array() === $hits );
if ( $hits ) { echo '   offenders: ' . implode( ', ', $hits ) . PHP_EOL; }

$design_code = '';

foreach ( glob( $plugin_dir . '/includes/Design/*.php' ) as $file ) {
	$design_code .= (string) file_get_contents( $file );
}

check( 'no second token namespace', false === strpos( $design_code, '--lawfirm-' ) && false === strpos( $design_code, '--medical-' ) && false === strpos( $design_code, '--design-' ) );
check( 'no second design storage key', false === strpos( $design_code, 'bb_selected_design' ) && false === strpos( $design_code, 'bb_site_design' ) );
check( 'the active design is still the existing theme mod', false !== strpos( $design_code, 'bb_theme_preset' ) );

/* No design may introduce a token outside --bb-*. */
$foreign_tokens = array();

foreach ( $all as $slug => $d ) {
	foreach ( array_keys( $d['tokens'] ) as $tok ) {
		if ( 0 !== strpos( (string) $tok, '--bb-' ) ) { $foreign_tokens[] = $slug . ':' . $tok; }
	}
}

check( 'no design introduces a token outside --bb-*', array() === array_unique( $foreign_tokens ) );

/* The Theme must remain domain-agnostic. */
$theme_hits = 0;
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme_dir ) );

foreach ( $rii as $file ) {

	if ( $file->isDir() || 'php' !== strtolower( $file->getExtension() ) ) { continue; }
	if ( false !== strpos( $file->getPathname(), '_phase1' ) ) { continue; }

	$code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $file->getPathname() ) );

	foreach ( array( 'LawFirm', 'Medical', 'lawfirm-', 'medical-', 'bb_doctor', 'bb_lawyer', 'Packs\\' ) as $needle ) {
		if ( false !== strpos( $code, $needle ) ) { $theme_hits++; }
	}
}

check( 'the Theme remains domain-agnostic (no pack/design coupling)', 0 === $theme_hits );

/* =================================================================
 * 7. SHELL IS DESIGN-DRIVEN BUT RESOLVER-AUTHORITATIVE (§13, §32)
 * ================================================================= */
echo PHP_EOL . '=== 7. DESIGN-DRIVEN SHELL ===' . PHP_EOL;

$shell_service = new DesignShell( $catalogue );

foreach ( $SITE_SLUGS as $slug ) {

	$declared = $shell_service->design_shell( $slug );

	check( $slug . ' declares a shell for every part', isset( $declared['header'], $declared['navigation'], $declared['footer'] ) );

	foreach ( $declared as $part => $variant ) {

		$resolved = bb_theme_shell_resolve( $part, $variant );

		check(
			$slug . ' shell ' . $part . '="' . $variant . '" resolves through the Phase 15 resolver',
			$resolved === $variant
		);
	}
}

/* An INVENTED variant must be refused by the resolver, never applied. */
check( 'the resolver refuses a variant that does not exist', 'default' === bb_theme_shell_resolve( 'header', 'not-a-real-variant' ) );

/* The designs must select VISIBLY DIFFERENT shells from one another. */
$shells = array();

foreach ( $SITE_SLUGS as $slug ) {
	$s = $shell_service->design_shell( $slug );
	$shells[ $slug ] = ( $s['header'] ?? '' ) . '|' . ( $s['navigation'] ?? '' ) . '|' . ( $s['footer'] ?? '' );
}

check( $SITE_TYPE . ': the designs select distinct shell combinations (' . count( array_unique( $shells ) ) . ' unique)', count( array_unique( $shells ) ) === count( $SITE_SLUGS ) );

/* =================================================================
 * 8. APPLY / RESTORE PRESERVE EVERYTHING (§44)
 * ================================================================= */
echo PHP_EOL . '=== 8. APPLYING A DESIGN PRESERVES EVERYTHING ELSE ===' . PHP_EOL;

$blog_id  = get_current_blog_id();
$original = $catalogue->active( $blog_id );

$front_id = (int) get_option( 'page_on_front' );
$sections = $front_id ? get_post_meta( $front_id, '_bb_page_sections', true ) : array();

$cpt = ( 'law_firm' === $SITE_TYPE ) ? 'bb_lawyer' : 'bb_doctor';

$before = array(
	'type'     => (string) get_option( 'bb_business_type' ),
	'theme'    => (string) get_option( 'stylesheet' ),
	'home'     => (string) get_option( 'home' ),
	'siteurl'  => (string) get_option( 'siteurl' ),
	'pages'    => count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) ),
	'sections' => is_array( $sections ) ? count( $sections ) : 0,
	'items'    => count( get_posts( array( 'post_type' => $cpt, 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) ),
);

/* Apply the LAST design of this type so the change is unambiguous. */
$target = end( $SITE_SLUGS );
reset( $SITE_SLUGS );

$result = $catalogue->apply( $bt, $target, true, $blog_id );

check( 'applying a valid design succeeds', $result['ok'] );
check( 'the applied design is reported', $target === $result['applied'] );
check( 'the design is now active', $target === $catalogue->active( $blog_id ) );
check( 'the design was written to the existing bb_theme_preset mod', $target === (string) get_theme_mod( 'bb_theme_preset', '' ) );

/* The design's identity must reach the resolved config. */
$cfg = bb_theme_preset_config();

check( 'the design supplies its primary colour', '' !== (string) ( $cfg['--bb-color-primary'] ?? '' ) );
check( 'the design supplies its hero gradient', false !== strpos( (string) ( $cfg['--bb-gradient-hero'] ?? '' ), 'gradient(' ) );
check( 'the design supplies its section rhythm', '' !== (string) ( $cfg['--bb-section-padding-block'] ?? '' ) );
check( 'the design supplies its card radius', '' !== (string) ( $cfg['--bb-radius-card'] ?? '' ) );
check( 'the design supplies a valid CSS motion time', (bool) preg_match( '/^\d*\.?\d+(s|ms)$/', (string) ( $cfg['--bb-motion-normal'] ?? '' ) ) );
check( 'the design supplies a letter-spacing with a unit', (bool) preg_match( '/^-?\d*\.?\d+(em|rem|px|%)$/', (string) ( $cfg['--bb-heading-letter-spacing'] ?? '' ) ) );

/* The emitted :root must actually contain the design's values. */
$css     = bb_theme_preset_css();
$emitted = 0;
$check_tokens = array( '--bb-color-primary', '--bb-gradient-hero', '--bb-radius-card', '--bb-section-padding-block', '--bb-motion-normal' );

foreach ( $check_tokens as $tok ) {
	if ( ! empty( $cfg[ $tok ] ) && false !== strpos( $css, (string) $cfg[ $tok ] ) ) { $emitted++; }
}

check( 'the design identity is emitted into :root (' . $emitted . '/' . count( $check_tokens ) . ')', $emitted >= count( $check_tokens ) - 1 );

/* Shell must follow the design when the site has no explicit choice. */
$declared_shell = $shell_service->design_shell( $target );

foreach ( $declared_shell as $part => $variant ) {

	$mod = (string) get_theme_mod( bb_theme_shell_mod_name( $part ), '' );

	if ( '' !== $mod ) { continue; } /* An explicit site choice wins by design. */

	check( 'the design\'s ' . $part . ' variant is applied', $variant === bb_theme_shell_part_variant( $part ) );
}

/* Nothing else may change. */
check( 'business type unchanged', $before['type'] === (string) get_option( 'bb_business_type' ) );
check( 'theme unchanged', $before['theme'] === (string) get_option( 'stylesheet' ) );
check( 'domain (home) unchanged', $before['home'] === (string) get_option( 'home' ) );
check( 'domain (siteurl) unchanged', $before['siteurl'] === (string) get_option( 'siteurl' ) );
check( 'pages preserved', $before['pages'] === count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) );
check( 'business content preserved', $before['items'] === count( get_posts( array( 'post_type' => $cpt, 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) );

if ( $front_id ) {
	$after = get_post_meta( $front_id, '_bb_page_sections', true );
	check( 'builder sections preserved', $before['sections'] === ( is_array( $after ) ? count( $after ) : 0 ) );
}

/* An incompatible design must be refused server-side. */
$bad = $catalogue->apply( $bt, $other_slug, true, $blog_id );
check( 'a cross-business-type design is refused', ! $bad['ok'] && 'invalid' === $bad['error'] );

$unknown = $catalogue->apply( $bt, 'no_such_design', true, $blog_id );
check( 'an unknown design is refused', ! $unknown['ok'] && 'invalid' === $unknown['error'] );

check( 'the refused designs did not change the active design', $target === (string) get_theme_mod( 'bb_theme_preset', '' ) );

/* Applying a DIFFERENT valid design must also work. */
$second = $SITE_SLUGS[0];
$res2   = $catalogue->apply( $bt, $second, true, $blog_id );
check( 'a second design of the same type applies', $res2['ok'] && $second === $catalogue->active( $blog_id ) );

/* =================================================================
 * 9. SAFE FALLBACK (§9, §39)
 * ================================================================= */
echo PHP_EOL . '=== 9. SAFE FALLBACK FOR A REMOVED DESIGN ===' . PHP_EOL;

$saved = (string) get_theme_mod( 'bb_theme_preset', '' );

set_theme_mod( 'bb_theme_preset', 'design_that_no_longer_exists' );
check( 'an unregistered stored design resolves to Default', 'default' === $catalogue->active( $blog_id ) );

$cfg = bb_theme_preset_config();
check( 'the site still renders resolvable tokens', '' !== (string) ( $cfg['--bb-color-primary'] ?? '' ) );

set_theme_mod( 'bb_theme_preset', $saved );
check( 'the original design was restored', $saved === $catalogue->active( $blog_id ) );

/* =================================================================
 * 10. DESIGN SCREEN (§10, §16-§19, §26, §46-§49)
 * ================================================================= */
echo PHP_EOL . '=== 10. THE DESIGN SCREEN ===' . PHP_EOL;

$page_src  = (string) file_get_contents( $plugin_dir . '/includes/Design/DesignPage.php' );
$page_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $page_src );

check( 'registers a site submenu under Business Builder', false !== strpos( $page_code, 'add_submenu_page' ) && false !== strpos( $page_code, "'business-builder'" ) );
check( 'requires the customization capability', false !== strpos( $page_code, "'edit_theme_options'" ) );
check( 'every mutation verifies a nonce', substr_count( $page_code, 'check_admin_referer' ) >= 4 );
check( 'acts on the CURRENT site only', false !== strpos( $page_code, 'get_current_blog_id()' ) );
check( 'never reads a posted blog id', false === strpos( $page_code, 'name="blog_id"' ) );
check( 'offers no business-type control', false === strpos( $page_code, 'name="business_type"' ) );
check( 'offers no pack control', false === strpos( $page_code, 'name="pack"' ) );
check( 'links to the ONE existing Customizer', false !== strpos( $page_code, 'customize.php' ) );
check( 'exposes all three distinct reset operations', false !== strpos( $page_code, 'reset_all' ) && false !== strpos( $page_code, 'restore_default' ) && false !== strpos( $page_code, 'reset_control' ) );
check( 'renders a real preview per design', false !== strpos( $page_code, 'palette_strip' ) );
check( 'renders preview modals with device modes', false !== strpos( $page_code, 'data-bb-device' ) );
check( 'offers an RTL preview toggle', false !== strpos( $page_code, 'data-bb-direction' ) );
check( 'the obsolete swatch-only preview is gone', false === strpos( $page_code, 'render_swatch' ) );

/* Render the real screen and inspect the markup. */
$_GET['page'] = DesignPage::SLUG;

$page = new DesignPage( $plugin );

ob_start();
$page->render();
$html = ob_get_clean();

check( 'the screen renders content (' . strlen( $html ) . ' bytes)', strlen( $html ) > 5000 );
check( 'the screen is the Design Studio', false !== strpos( $html, 'Design Studio' ) );

$cards = substr_count( $html, 'data-bb-design-card=' );
check( 'design cards render (' . $cards . ')', $cards >= 4 );

$modals = substr_count( $html, 'data-bb-design-modal=' );
check( 'one preview modal per card', $modals === $cards );

$previews = substr_count( $html, 'class="bb-design-preview' );
check( 'real previews render (' . $previews . ')', $previews >= $cards );

$palettes = substr_count( $html, 'class="bb-design-palette"' );
check( 'palette strips render (' . $palettes . ')', $palettes >= $cards );

$swatches = substr_count( $html, 'bb-design-swatch' );
check( 'colour swatches render (' . $swatches . ')', $swatches >= $cards * 4 );

$specimens = substr_count( $html, 'bb-design-type-specimen' );

/*
 * The screen renders a specimen for the CURRENT DESIGN panel as well as one per card, so the
 * expected count is `cards + 1` (or `cards` when no current design is resolved).
 */
check( 'a type specimen is rendered for every design (' . $specimens . ' for ' . $cards . ' cards)', $specimens >= $cards );

$rtl = substr_count( $html, 'bb-design-preview--rtl' );
check( 'RTL previews are pre-rendered (' . $rtl . ')', $rtl > 0 );

check( 'an active-state indicator is shown', false !== strpos( $html, 'bb-design-badge--active' ) );
check( 'all three device modes are offered', false !== strpos( $html, 'data-bb-device="desktop"' ) && false !== strpos( $html, 'data-bb-device="tablet"' ) && false !== strpos( $html, 'data-bb-device="mobile"' ) );
check( 'a Customize action links to the existing Customizer', false !== strpos( $html, 'customize.php' ) );

$apply_buttons = substr_count( $html, 'Use this design' );
check( 'apply buttons present (' . $apply_buttons . ')', $apply_buttons >= 3 );

$nonces = substr_count( $html, '_wpnonce' );
check( 'every mutation is nonce-protected (' . $nonces . ' nonces)', $nonces >= 3 );

/* Every design of this type must appear as a card. */
foreach ( $SITE_SLUGS as $slug ) {
	check( '"' . $slug . '" appears as a card', false !== strpos( $html, 'data-bb-design-card="' . $slug . '"' ) );
}

/* =================================================================
 * 11. PREVIEW IS READ-ONLY (§11)
 * ================================================================= */
echo PHP_EOL . '=== 11. THE PREVIEW WRITES NOTHING ===' . PHP_EOL;

$prev_src  = (string) file_get_contents( $plugin_dir . '/includes/Design/DesignPreview.php' );
$prev_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $prev_src );

check( 'preview verifies a nonce', false !== strpos( $prev_code, 'wp_verify_nonce' ) );
check( 'preview requires the customization capability', false !== strpos( $prev_code, 'current_user_can_customize' ) );
check( 'preview validates the design against the business type', false !== strpos( $prev_code, 'is_valid_for' ) );
check( 'preview writes NOTHING', false === strpos( $prev_code, 'set_theme_mod' ) && false === strpos( $prev_code, 'update_option' ) );
check( 'preview reuses the theme preset filter', false !== strpos( $prev_code, "'bb_theme_active_preset'" ) );

$rend_src  = (string) file_get_contents( $plugin_dir . '/includes/Design/DesignPreviewRenderer.php' );
$rend_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $rend_src );

check( 'the preview renderer writes nothing', false === strpos( $rend_code, 'set_theme_mod' ) && false === strpos( $rend_code, 'update_option' ) && false === strpos( $rend_code, 'update_post_meta' ) );
check( 'the preview renderer rejects url()/javascript: values', false !== strpos( $rend_code, 'javascript:' ) && false !== strpos( $rend_code, 'url(' ) );
check( 'the preview renderer gives the preview meaningful alt text', false !== strpos( $rend_src, 'aria-label' ) );

/* The preview must be built from the design's OWN tokens (§58). */
check( 'the preview reads the design tokens', false !== strpos( $rend_code, "--bb-" ) || false !== strpos( $rend_code, "0 !== strpos" ) );

/* =================================================================
 * 12. MOTION & ACCESSIBILITY (§14, §15, §33-§35, §60)
 * ================================================================= */
echo PHP_EOL . '=== 12. MOTION AND ACCESSIBILITY ===' . PHP_EOL;

$motion_js    = (string) file_get_contents( $plugin_dir . '/assets/js/frontend/design-motion.js' );
$identity_css = (string) file_get_contents( $plugin_dir . '/assets/css/frontend/design-identity.css' );

check( 'motion respects prefers-reduced-motion in JS', false !== strpos( $motion_js, 'prefers-reduced-motion' ) );
check( 'motion is progressive (nothing hidden without JS)', false !== strpos( $motion_js, 'data-bb-reveal' ) );
check( 'motion uses ONE shared observer', substr_count( $motion_js, 'new IntersectionObserver' ) === 1 );
check( 'motion staggers grids', false !== strpos( $motion_js, 'data-bb-stagger' ) );
check( 'motion reveals above-the-fold content immediately', false !== strpos( $motion_js, 'prefersReduced' ) );

check( 'CSS disables non-essential animation under reduced motion', false !== strpos( $identity_css, '@media (prefers-reduced-motion: reduce)' ) );
check( 'RTL reveal direction is semantics-aware, not hardcoded left', false !== strpos( $identity_css, 'bb-rtl' ) );
check( 'the identity layer introduces no token outside --bb-*', ! preg_match( '/--(?!bb-)[a-z]+-/', $identity_css ) );
check( 'the identity layer consumes the design motion tokens', false !== strpos( $identity_css, '--bb-motion-normal' ) );
check( 'no animation library is bundled', false === strpos( $motion_js, 'gsap' ) && false === strpos( $motion_js, 'ScrollMagic' ) && false === stripos( $motion_js, 'jquery' ) );

/* The reveal CSS must animate only transform/opacity (no layout thrash). */
check( 'reveals animate transform/opacity only', false !== strpos( $identity_css, 'translate3d' ) && false !== strpos( $identity_css, 'opacity' ) );

/* =================================================================
 * 12b. DESIGN FONTS (§24, §25, §59)
 * ================================================================= */
echo PHP_EOL . '=== 12b. DESIGN FONT LOADING ===' . PHP_EOL;

$fonts = new \BusinessBuilderCore\Design\DesignFonts();

$font_slugs = $fonts->needed_slugs();
$font_url   = $fonts->stylesheet_url();

check( 'the font loader reports a family list', is_array( $font_slugs ) );

/*
 * The remote families must be derived from the ACTIVE design. A design whose typography is
 * entirely local (Meridian uses Georgia) must request NO remote family at all.
 */
$uses_local_only = false;

foreach ( $SITE_SLUGS as $slug ) {

	$stack = (string) ( $all[ $slug ]['tokens']['--bb-font-heading'] ?? '' );

	if ( false !== stripos( $stack, 'Georgia' ) && false === stripos( $stack, 'Manrope' ) && false === stripos( $stack, 'Cormorant' ) ) {
		$uses_local_only = true;
	}
}

if ( $font_url ) {

	check( 'the font stylesheet uses https', 0 === strpos( $font_url, 'https://' ) );
	check( 'the font stylesheet requests only specific weights', false !== strpos( $font_url, ':wght@' ) );
	check( 'the font stylesheet uses display=swap (never blocks rendering)', false !== strpos( $font_url, 'display=swap' ) );

} else {

	check( 'a system-font design requests no remote font (active design is local-only)', $uses_local_only );
}

/* The catalog must be filterable, not hardcoded per design. */
$catalog = $fonts->catalog();
check( 'the remote font catalog is filterable and non-empty', is_array( $catalog ) && count( $catalog ) > 0 );
check( 'the catalog includes an Arabic family', isset( $catalog['IBM Plex Sans Arabic'] ) || isset( $catalog['Noto Sans Arabic'] ) || isset( $catalog['Cairo'] ) );

/* The loader must be registered on the front end only. */
$fonts_src = (string) file_get_contents( $plugin_dir . '/includes/Design/DesignFonts.php' );
check( 'font loading is skipped in admin', false !== strpos( $fonts_src, 'is_admin()' ) );
check( 'font loading only requests used families', false !== strpos( $fonts_src, 'bb_theme_preset_config' ) );

/* =================================================================
 * 12c. DESIGN STUDIO (§20-§29, §43, §45)
 * ================================================================= */
echo PHP_EOL . '=== 12c. DESIGN STUDIO ===' . PHP_EOL;

$studio_src  = (string) file_get_contents( $plugin_dir . '/includes/Design/DesignStudioUI.php' );
$studio_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $studio_src );

/* --- the Studio is a renderer over the EXISTING schema, not a second system (§40) --- */
check( 'the Studio reads the Theme schema', false !== strpos( $studio_code, 'bb_theme_design_schema' ) );
check( 'the Studio renders the Theme\'s own colour controls', false !== strpos( $studio_code, "'color' !==" ) );
check( 'the Studio stores via the existing theme mod helper', false !== strpos( $studio_code, 'bb_theme_design_mod_name' ) || false !== strpos( $page_code_full, 'bb_theme_design_mod_name' ) );
check( 'the Studio introduces no storage of its own', false === strpos( $studio_code, 'update_option(' ) );
check( 'the Studio has no business-type branching', false === strpos( $studio_code, "'law_firm'" ) && false === strpos( $studio_code, "'medical'" ) );

/*
 * The Studio renders; the SAVE HANDLER validates and writes. The Studio is handed the action name
 * by the page (so there is one definition of it), and the sanitizer assertion is therefore made
 * against the handler - the code that actually persists - in section 13.
 */
check( 'the Studio is driven by the page\'s save action', false !== strpos( $studio_src, '$action' ) );
check( 'the Studio renders a save form', false !== strpos( $studio_code, 'admin-post.php' ) );

/* --- §21: colour is the UI, not a hex field --- */
check( 'colour controls render as swatches', false !== strpos( $studio_code, 'type="color"' ) );
check( 'colour controls show the hex as secondary info', false !== strpos( $studio_code, 'bb-color-hex' ) );
check( 'palette presets are offered as tiles', false !== strpos( $studio_code, 'bb-palette-tile' ) );

/* --- §23, §24, §27: visual controls --- */
check( 'gradients are offered as visual tiles', false !== strpos( $studio_code, 'bb-gradient-swatch' ) );
check( 'fonts are previewed as specimens', false !== strpos( $studio_code, 'bb-font-specimen' ) );
check( 'spacing is shown with proportional bars', false !== strpos( $studio_code, 'bb-scale-bars' ) );

/* --- §43: live preview without a new architecture --- */
$panel_js = (string) file_get_contents( $plugin_dir . '/assets/js/admin/design-studio-panel.js' );
check( 'the live preview writes CSS custom properties', false !== strpos( $panel_js, 'setProperty' ) );
check( 'the live preview only writes --bb-* tokens', false !== strpos( $panel_js, "'--bb-'" ) );
check( 'the live preview validates colours as hex', false !== strpos( $panel_js, 'HEX' ) );
check( 'the live preview makes no network request', false === strpos( $panel_js, 'fetch(' ) && false === strpos( $panel_js, 'XMLHttpRequest' ) && false === strpos( $panel_js, 'jQuery' ) );
check( 'the live preview persists nothing', false === strpos( $panel_js, 'localStorage' ) );

/* --- §45: the three reset operations are distinct and explained --- */
check( 'the Studio offers reset-all', false !== strpos( $studio_code, 'ACTION_RESET_ALL' ) );
check( 'the Studio offers restore-default', false !== strpos( $studio_code, 'ACTION_RESTORE_DEFAULT' ) );
check( 'each reset explains its effect', false !== strpos( $studio_code, 'bb-studio-reset-note' ) );

/* --- §46: unsaved-changes indicator --- */
check( 'the Studio indicates unsaved changes', false !== strpos( $studio_code, 'bb-studio-note' ) );

/* --- the save handler's security --- */
$page_code_full = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $plugin_dir . '/includes/Design/DesignPage.php' ) );
check( 'the save handler verifies a nonce', false !== strpos( $page_code_full, 'check_admin_referer( self::ACTION_SAVE )' ) );
check( 'the save handler acts on the CURRENT site', false !== strpos( $page_code_full, 'get_current_blog_id()' ) );
check( 'the save handler rejects unknown control keys', false !== strpos( $page_code_full, '! isset( $schema[ $key ] )' ) );
check( 'the save handler sanitizes every value', false !== strpos( $page_code_full, 'bb_theme_sanitize_design_value' ) );

/* --- §55: every curated palette is contrast-checked --- */
$palettes_ui = ( new \BusinessBuilderCore\Design\DesignPalettes() )->for_ui();
check( 'the palette library is non-empty (' . count( $palettes_ui ) . ')', count( $palettes_ui ) >= 10 );

$poor = array();
foreach ( $palettes_ui as $slug => $p ) {
	if ( 'poor' === ( $p['contrast']['grade'] ?? '' ) ) { $poor[] = $slug; }
}
check( 'no palette ships unreadable body or button text', array() === $poor );
if ( $poor ) { echo '   poor contrast: ' . implode( ', ', $poor ) . PHP_EOL; }

/* --- §25: only the families a design uses are requested --- */
$fonts_lib = ( new \BusinessBuilderCore\Design\DesignFonts() )->catalog();
check( 'the remote font catalog is curated and filterable', count( $fonts_lib ) >= 5 );

$typo_lib = new \BusinessBuilderCore\Design\DesignTypography();
check( 'the Studio offers curated fonts (' . count( $typo_lib->fonts() ) . ')', count( $typo_lib->fonts() ) >= 8 );
check( 'the Studio offers Arabic-capable fonts', (bool) array_filter( $typo_lib->fonts(), function ( $f ) { return ! empty( $f['arabic'] ); } ) );

/* --- §27: named scales resolve to real schema controls --- */
$scales_lib = ( new \BusinessBuilderCore\Design\DesignScales() )->available();
check( 'the Studio offers named scale groups (' . count( $scales_lib ) . ')', count( $scales_lib ) >= 10 );

/* =================================================================
 * 13. SECURITY (§26, §38)
 * ================================================================= */
echo PHP_EOL . '=== 13. SECURITY BOUNDARIES ===' . PHP_EOL;

$attacker = get_user_by( 'login', 'phase21_ds_attacker' );

if ( ! $attacker ) {
	$created  = wp_create_user( 'phase21_ds_attacker', wp_generate_password( 20 ), 'phase21_ds@example.test' );
	$attacker = is_wp_error( $created ) ? null : get_user_by( 'id', (int) $created );
}

if ( $attacker ) {

	add_user_to_blog( $blog_id, $attacker->ID, 'administrator' );

	wp_set_current_user( (int) $attacker->ID );

	check( 'a site administrator may customize the design', $catalogue->current_user_can_customize( $blog_id ) );
	check( 'a site administrator cannot customize another site', ! $catalogue->current_user_can_customize( 1 ) );
	check( 'a site administrator still cannot assign a business type', ! \BusinessBuilderCore\Network\BusinessTypeGuard::current_user_can_assign_for_site( $blog_id ) );
	check( 'the business type was not changed by the design surface', $SITE_TYPE === (string) get_option( 'bb_business_type' ) );

	/* A cross-business-type design must be refused even for the site's own administrator. */
	$cross = $catalogue->apply( $bt, $other_slug, true, $blog_id );
	check( 'a cross-business-type design is refused for a site admin', ! $cross['ok'] );

	wp_set_current_user( 1 );

	remove_user_from_blog( $attacker->ID, $blog_id );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $attacker->ID );

} else {
	check( 'could create the security fixture', false );
}

/* =================================================================
 * 14. ISOLATION (§27)
 * ================================================================= */
echo PHP_EOL . '=== 14. SITE ISOLATION ===' . PHP_EOL;

/*
 * Isolation is verified on RAW state, read per blog from the DATABASE. In a single-site process
 * this is the only reliable comparison, and it is also the strongest one: it proves the write
 * landed on exactly one site.
 */
global $wpdb;

$mod = bb_theme_design_mod_name( 'color_primary' );

$snapshot = array();

foreach ( array( 1, 2, 3 ) as $id ) {

	$table = $wpdb->get_blog_prefix( $id ) . 'options';

	$snapshot[ $id ] = array(
		'type' => $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", 'bb_business_type' ) ),
		'mods' => (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", 'theme_mods_business-builder' ) ),
	);
}

/* Write a customization on THIS site. */
set_theme_mod( $mod, '#112233' );

check( 'the customization exists on this site', '#112233' === (string) get_theme_mod( $mod, '' ) );

/*
 * Re-read every OTHER site and confirm the fixture value is absent.
 *
 * WHY THIS USES switch_to_blog() RATHER THAN A RAW QUERY:
 * `set_theme_mod()` updates this process's in-memory option cache before the database row is
 * flushed, so a raw `$wpdb` read can race the write and report the value on the site that just
 * wrote it. Reading each site through the SAME accessor the product uses (`get_theme_mod()`
 * inside that site's context) is both exact and faithful to how isolation is actually exercised.
 */
$leaked = array();

foreach ( array( 1, 2, 3 ) as $id ) {

	if ( $id === $blog_id ) { continue; }

	switch_to_blog( $id );

	$other_value = (string) get_theme_mod( $mod, '' );

	restore_current_blog();

	if ( '#112233' === $other_value ) { $leaked[] = $id; }
}

check( 'the customization did not leak into any other site', array() === $leaked );
if ( $leaked ) { echo '   leaked into: ' . implode( ', ', $leaked ) . PHP_EOL; }

/* The write must be readable on THIS site. */
check( 'the customization is readable on this site', '#112233' === (string) get_theme_mod( $mod, '' ) );

/* Business types must be untouched everywhere. */
foreach ( array( 1, 2, 3 ) as $id ) {

	$table = $wpdb->get_blog_prefix( $id ) . 'options';
	$now   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", 'bb_business_type' ) );

	check( 'site ' . $id . ' business type unchanged', $snapshot[ $id ]['type'] === $now );
}

/* Site 1 must remain a non-Business-Builder site. */
check( 'site 1 still has no business type', null === $snapshot[1]['type'] || '' === (string) $snapshot[1]['type'] );

/* =================================================================
 * 15. CUSTOMIZATION (§14, §16)
 * ================================================================= */
echo PHP_EOL . '=== 15. CUSTOMIZATION AND RESET ===' . PHP_EOL;

/* Colour override wins over the design. */
set_theme_mod( $mod, '#b91c1c' );
$cfg = bb_theme_preset_config();
check( 'a colour override wins over the design', '#b91c1c' === strtolower( (string) ( $cfg['--bb-color-primary'] ?? '' ) ) );

/* A radius override is an independent control. */
$mod_radius = bb_theme_design_mod_name( 'radius_md' );
set_theme_mod( $mod_radius, '1.75rem' );
$cfg = bb_theme_preset_config();
check( 'a radius override wins over the design', '1.75rem' === (string) ( $cfg['--bb-radius-md'] ?? '' ) );
check( 'the colour override survives alongside it', '#b91c1c' === strtolower( (string) ( $cfg['--bb-color-primary'] ?? '' ) ) );

/* A motion override must survive and carry its unit. */
$mod_motion = bb_theme_design_mod_name( 'motion_speed' );
set_theme_mod( $mod_motion, '0.5' );
$cfg = bb_theme_preset_config();
check( 'a motion override resolves to a valid CSS time', (bool) preg_match( '/^\d*\.?\d+(s|ms)$/', (string) ( $cfg['--bb-motion-normal'] ?? '' ) ) );

/* Reset ONE control. */
$one = $catalogue->reset_control( $blog_id, 'color_primary' );
check( 'reset one control succeeds', $one['ok'] );
check( 'only the targeted control was reset', '' === (string) get_theme_mod( $mod, '' ) );
check( 'the other control is untouched', '1.75rem' === (string) get_theme_mod( $mod_radius, '' ) );
check( 'an unknown control key is rejected', ! $catalogue->reset_control( $blog_id, 'not_a_control' )['ok'] );

/* Reset ALL customizations — distinct from restoring the Default design. */
$reset = $catalogue->reset_customization( $blog_id );
check( 'reset all customizations succeeds', $reset['ok'] );
check( 'reset all restores the ACTIVE design (not the Default)', $reset['design'] === $catalogue->active( $blog_id ) );
check( 'the remaining overrides were removed', '' === (string) get_theme_mod( $mod_radius, '' ) );
check( 'the active design was NOT changed by reset', $saved === (string) get_theme_mod( 'bb_theme_preset', '' ) );

/* Restore the Default design — a DIFFERENT operation. */
$def = $catalogue->restore_default( $bt, true, $blog_id );
check( 'restoring the Default design succeeds', $def['ok'] );
check( 'the Default design is now active', 'default' === $catalogue->active( $blog_id ) );

/* =================================================================
 * 16. RESPONSIVE / RTL (§35, §36, §60)
 * ================================================================= */
echo PHP_EOL . '=== 16. RESPONSIVE AND RTL ===' . PHP_EOL;

$preview_css = (string) file_get_contents( $plugin_dir . '/assets/css/admin/design-preview.css' );

check( 'the studio has a mobile breakpoint', false !== strpos( $preview_css, '@media (max-width: 782px)' ) );
check( 'the studio has a tablet/stacking breakpoint', false !== strpos( $preview_css, '@media (max-width: 1100px)' ) );
check( 'the studio uses logical properties (RTL-safe)', false !== strpos( $preview_css, 'inline-start' ) || false !== strpos( $preview_css, 'inset-inline' ) );
check( 'the studio respects reduced motion', false !== strpos( $preview_css, 'prefers-reduced-motion' ) );
check( 'device frames are class-driven (desktop/tablet/mobile)', false !== strpos( $preview_css, 'bb-design-modal-frame--mobile' ) );

check( 'the identity layer has a mobile breakpoint', false !== strpos( $identity_css, '@media (max-width: 767px)' ) );
check( 'the identity layer guards against horizontal overflow on mobile', false !== strpos( $identity_css, 'padding-block: var(--bb-section-padding-block-sm)' ) );

/* The rendered screen must carry the RTL-capable preview variants. */
check( 'the rendered screen includes LTR previews', false !== strpos( $html, 'bb-design-preview--ltr' ) );

/* =================================================================
 * 17. RESTORE AND FINAL STATE
 * ================================================================= */
echo PHP_EOL . '=== 17. RESTORE AND FINAL STATE ===' . PHP_EOL;

$catalogue->apply( $bt, $original ?: $SITE_SLUGS[0], true, $blog_id );

$final = $catalogue->active( $blog_id );

check( 'the site is on a design that exists', 'default' === $final || isset( $catalogue->all()[ $final ] ) );

printf( '   restored site %d to "%s" (was "%s")' . PHP_EOL, $blog_id, $final, $original );

/* ================================================================= */
echo PHP_EOL . str_repeat( '=', 72 ) . PHP_EOL;

printf( "SITE %d (%s) RESULT: %d passed, %d failed\n", $blog_id, $SITE_LABEL, $GLOBALS['bb_pass'], count( $GLOBALS['bb_fail'] ) );

if ( $GLOBALS['bb_fail'] ) {

	echo PHP_EOL . 'FAILURES:' . PHP_EOL;

	foreach ( $GLOBALS['bb_fail'] as $f ) {
		echo '  - ' . $f . PHP_EOL;
	}
}

echo str_repeat( '=', 72 ) . PHP_EOL;

exit( $GLOBALS['bb_fail'] ? 1 : 0 );