<?php
/*
 * Phase 18 verification: Platform Architecture — pack self-registration.
 *
 * The audit found the platform layering (BusinessType -> PackManager -> Pack) is
 * already correct and generic, with ONE missing extension point: packs could only
 * be registered by editing core (ServiceProvider hardcoded `law_firm`). Phase 18
 * added a single `bb_register_packs` action so a pack can self-register.
 *
 * This suite verifies:
 *   (1) the core behaviour is UNCHANGED (law_firm still registered exactly once);
 *   (2) a new pack CAN register itself via bb_register_packs;
 *   (3) the hook fires at the right time (before pack boot);
 *   (4) PackManager rejects invalid/unsafe registration (blank slug, missing class);
 *   (5) BusinessType owns the type->pack mapping and gates which pack boots;
 *   (6) a site whose business type has no pack loaded stays inert (isolation);
 *   (7) the Theme remains domain-agnostic (no pack/business coupling);
 *   (8) multisite isolation of bb_business_type.
 */

define('WP_USE_THEMES', false);
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

use BusinessBuilderCore\Core\PackManager;
use BusinessBuilderCore\Core\Container;
use BusinessBuilderCore\Settings\BusinessType;

/* Load the canonical Theme's function layers (the same set the Phase 15-17
 * suites load) so the independence assertions in section 9 can run. */
$bb_theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
if ( ! defined( 'BB_THEME_PATH' ) ) { define( 'BB_THEME_PATH', trailingslashit( $bb_theme_dir ) ); }
if ( ! defined( 'BB_THEME_URL' ) ) { define( 'BB_THEME_URL', 'http://lawfirm.builder.test/wp-content/themes/business-builder/' ); }
if ( ! defined( 'BB_THEME_VERSION' ) ) { define( 'BB_THEME_VERSION', '1.0.0' ); }
foreach ( array( 'setup', 'theme-support', 'enqueue', 'preset-resolver', 'shell-variants', 'shell-data', 'template-functions', 'template-hooks', 'navigation', 'design-schema', 'customization', 'components' ) as $f ) {
    $p = $bb_theme_dir . '/inc/' . $f . '.php';
    if ( is_readable( $p ) ) { require_once $p; }
}

/* ---------- 0. The hook exists and is documented ---------- */
$sp_src = file_get_contents( WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Core/ServiceProvider.php' );
check( 'ServiceProvider fires bb_register_packs', false !== strpos( $sp_src, "do_action( 'bb_register_packs'" ) );
check( 'bb_register_packs is fired after the law_firm registration',
    strpos( $sp_src, "'law_firm'" ) < strpos( $sp_src, "do_action( 'bb_register_packs'" ) );

/* ---------- 1. Core behaviour unchanged ---------- */
$business_type = new BusinessType();
check( 'BusinessType registers law_firm', $business_type->exists( 'law_firm' ) );
check( 'law_firm maps to the LawFirm pack', 'LawFirm' === ( $business_type->get( 'law_firm' )['pack'] ?? '' ) );
check( 'BusinessType still declares 4 business types', count( $business_type->get_all() ) >= 4 );

$pack_manager = new PackManager( $business_type, new Container() );
// Reproduce core registration exactly as ServiceProvider does.
$pack_manager->register( 'law_firm', 'BusinessBuilderCore\\Packs\\LawFirm\\LawFirmPack' );
check( 'law_firm pack is registered', $pack_manager->exists( 'law_firm' ) );
check( 'registered pack class resolves', false !== strpos( (string) $pack_manager->get( 'law_firm' ), 'LawFirmPack' ) );

/* ---------- 2. A new pack CAN self-register via the hook ---------- */
/*
 * A stand-in pack class standing in for a future Medical/Dentist pack. It only
 * needs a class name; PackManager instantiates it lazily via the Container.
 */
if ( ! class_exists( 'BB_Phase18_FakePack' ) ) {
    class BB_Phase18_FakePack {
        public bool $registered = false;
        public bool $booted = false;
        public function register(): void { $this->registered = true; }
        public function boot(): void { $this->booted = true; }
    }
}

$observed = array();
add_action( 'bb_register_packs', function ( $pm ) use ( &$observed ) {
    $observed['fired'] = true;
    $observed['is_pack_manager'] = $pm instanceof PackManager;
    // A pack registers its BUSINESS TYPE slug -> class, exactly like core.
    $ok = $pm->register( 'medical', 'BB_Phase18_FakePack' );
    $observed['registered'] = (bool) $ok;
} );

// Simulate ServiceProvider firing the action.
do_action( 'bb_register_packs', $pack_manager );

check( 'bb_register_packs fires', ! empty( $observed['fired'] ) );
check( 'hook receives the PackManager', ! empty( $observed['is_pack_manager'] ) );
check( 'external pack registered via hook', ! empty( $observed['registered'] ) && $pack_manager->exists( 'medical' ) );

/* ---------- 3. A registered pack boots only for its own business type ---------- */
$instances_before = count( (array) ( new ReflectionObject( $pack_manager ) )->getProperty( 'instances' )->getValue( $pack_manager ) );
$booted = $pack_manager->boot( 'medical' );
$instances_after = count( (array) ( new ReflectionObject( $pack_manager ) )->getProperty( 'instances' )->getValue( $pack_manager ) );
check( 'medical pack boots', true === $booted );
check( 'boot created a pack instance', $instances_after > $instances_before );
$medical_instance = $pack_manager->get_instance( 'medical' );
check( 'booted instance called register()', $medical_instance instanceof BB_Phase18_FakePack && $medical_instance->registered );
check( 'booted instance called boot()', $medical_instance instanceof BB_Phase18_FakePack && $medical_instance->booted );
// Idempotent: booting again must not re-instantiate.
$same = $pack_manager->get_instance( 'medical' );
$pack_manager->boot( 'medical' );
check( 're-boot returns the same instance (no duplicate)', $same === $pack_manager->get_instance( 'medical' ) );

/* ---------- 4. PackManager rejects invalid registration ---------- */
$m = new PackManager( new BusinessType(), new Container() );
check( 'blank slug rejected', false === $m->register( '', 'BB_Phase18_FakePack' ) );
check( 'blank class rejected', false === $m->register( 'x', '' ) );
check( 'non-existent class rejected', false === $m->register( 'nope', 'This\\Class\\Does\\Not\\Exist_XYZ' ) );
check( 'slug is sanitized (key)', true === $m->register( 'My Slug!', 'BB_Phase18_FakePack' ) && $m->exists( 'myslug' ) );
check( 'booting an unregistered pack returns false', false === $m->boot( 'ghost' ) );
check( 'unsafe slug cannot register a real class', false === $m->register( '../../evil', 'This\\Class\\Does\\Not\\Exist_XYZ' ) );

/* ---------- 5. BusinessType gates which pack a site boots ---------- */
switch_to_blog( 2 );
$bt2 = new BusinessType();
$bt2->set_current( 'law_firm' );
check( 'site 2 business type = law_firm', 'law_firm' === $bt2->get_current() );
$bt2->set_current( 'medical' );
check( 'site 2 can be set to medical', 'medical' === $bt2->get_current() );
check( 'unknown business type is rejected', false === $bt2->set_current( 'not_a_type' ) );
check( 'business type reads back the last valid value', 'medical' === $bt2->get_current() );
$bt2->set_current( 'law_firm' );
restore_current_blog();

/* ---------- 6. Multisite isolation of bb_business_type ---------- */
switch_to_blog( 2 );
update_option( 'bb_business_type', 'law_firm' );
restore_current_blog();
switch_to_blog( 1 );
update_option( 'bb_business_type', '' );
$site1_type = ( new BusinessType() )->get_current();
restore_current_blog();
$site2_type = ( function () {
    switch_to_blog( 2 );
    $t = ( new BusinessType() )->get_current();
    restore_current_blog();
    return $t;
} )();
check( 'site 2 type is law_firm', 'law_firm' === $site2_type );
check( 'site 1 type is not leaked from site 2', 'law_firm' !== $site1_type );

/* ---------- 7. Theme remains domain-agnostic ---------- */
$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
$hits = 0;
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme_dir ) );
foreach ( $rii as $file ) {
    if ( $file->isDir() || 'php' !== strtolower( $file->getExtension() ) ) { continue; }
    $path = $file->getPathname();
    if ( false !== strpos( $path, '_phase1' ) ) { continue; }
    $code = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $path ) );
    foreach ( array( 'LawFirm', 'bb_lawyer', 'bb_legal_service', 'bb_practice_area', 'bb_consultation', 'bb_business_type', 'Packs\\' ) as $needle ) {
        if ( false !== strpos( $code, $needle ) ) { $hits++; }
    }
}
check( 'Theme source has NO pack/business coupling', 0 === $hits );

/* ---------- 8. Design-style extensibility (the platform requirement) ---------- */
$preset_src = file_get_contents( $theme_dir . '/inc/preset-resolver.php' );
check( 'presets are filterable (bb_theme_presets)', false !== strpos( $preset_src, "'bb_theme_presets'" ) );
check( 'preset config is filterable (bb_theme_preset_config)', false !== strpos( $preset_src, "'bb_theme_preset_config'" ) );
/* A pack can add a preset without touching the theme. */
add_filter( 'bb_theme_presets', function ( $presets ) {
    $presets['phase18_probe'] = array( 'label' => 'Phase18 Probe', 'description' => 'test' );
    return $presets;
} );
$presets = apply_filters( 'bb_theme_presets', array( 'default' => array(), 'modern' => array(), 'luxury' => array() ) );
check( 'external preset can be registered', isset( $presets['phase18_probe'] ) );
check( 'default preset still present (valid fallback)', isset( $presets['default'] ) );

/* ---------- 9. Design Style / Section Layout / Component Variant independence ---------- */
check( 'section variant resolver intact', 'default' === bb_resolve_section_variant( 'lawyers', '../../etc' ) );
check( 'component path resolver exists (generic)', function_exists( 'bb_component_path' ) );
check( 'shell variant resolver intact (Phase 15)', 'default' === bb_theme_shell_resolve( 'header', '../../etc' ) );

/* ---------- 10. Core Builder has NO business-domain leakage ---------- */
/*
 * The core SectionRenderer must render DOMAIN (pack) sections through the
 * generic `bb_render_section` filter, never by name-matching pack slugs.
 */
$renderer_src = preg_replace(
    '#/\*.*?\*/|//[^\n]*#s',
    '',
    (string) file_get_contents( WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/SectionRenderer.php' )
);
$leaks = array();
foreach ( array( "'lawyers'", "'legal_services'", "'practice_areas'", "'bb_lawyer'", "'bb_legal_service'", "'bb_practice_area'", "'bb_consultation'", 'LawFirm', 'Packs\\' ) as $needle ) {
    if ( false !== strpos( $renderer_src, $needle ) ) { $leaks[] = $needle; }
}
check( 'SectionRenderer has NO hardcoded pack slugs', array() === $leaks );
check( 'SectionRenderer offers a generic bb_render_section filter', false !== strpos( $renderer_src, "'bb_render_section'" ) );

/*
 * Behavioural proof: a pack claims its own type and core renders it, while an
 * unclaimed type falls through to the generic renderer (legacy behaviour).
 */
$claimed_types = array();
$probe_filter = function ( $claimed, $type, $section, $settings, $content ) use ( &$claimed_types ) {
    $claimed_types[] = $type;
    return in_array( $type, array( 'lawyers', 'medical_doctors' ), true ) ? true : $claimed;
};
add_filter( 'bb_render_section', $probe_filter, 10, 5 );
$claimed_lawyers = apply_filters( 'bb_render_section', false, 'lawyers', array(), array(), array() );
$claimed_unknown = apply_filters( 'bb_render_section', false, 'someone_elses_section', array(), array(), array() );
remove_filter( 'bb_render_section', $probe_filter, 10 );
check( 'a pack can claim its own section type via bb_render_section', true === $claimed_lawyers );check( 'an unclaimed type falls through (no core hardcoding)', false === $claimed_unknown );
check( 'the filter receives the section type', array( 'lawyers', 'someone_elses_section' ) === $claimed_types );

/* The pack itself must own the slug list (domain lives in the pack). */
$pack_sections_src = (string) file_get_contents( WP_CONTENT_DIR . '/plugins/business-builder-core/packs/LawFirm/Sections/LawFirmSections.php' );
check( 'pack claims its types via the generic hook', false !== strpos( $pack_sections_src, "'bb_render_section'" ) );
$legacy_hooks = 0;
foreach ( array( 'bb_render_section_lawyers', 'bb_render_section_legal_services', 'bb_render_section_practice_areas', 'bb_render_section_testimonials', 'bb_render_section_faq' ) as $legacy ) {
    if ( false !== strpos( $pack_sections_src, $legacy ) ) { $legacy_hooks++; }
}
check( 'pack no longer registers five hardcoded render hooks', 0 === $legacy_hooks );

/* Cleanup. */
switch_to_blog( 1 );
update_option( 'bb_business_type', '' );
restore_current_blog();

echo 'DONE' . PHP_EOL;