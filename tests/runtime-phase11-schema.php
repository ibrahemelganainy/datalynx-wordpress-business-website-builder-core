<?php
/*
 * Phase 11: the variant setting is exposed in the builder schema and the
 * existing AJAX select-sanitizer whitelists it (no builder change needed).
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

switch_to_blog( 2 );

/* Boot the plugin's registry the way the plugin does. */
$registry = new \BusinessBuilderCore\Builder\SectionRegistry();
$sections = new \BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections( $registry );

/* Register variants (the plugin fires this at init) before the schema reads
 * the options via bb_section_variant_options(). */
if ( function_exists( 'bb_section_variants' ) ) {
    $sections->register_section_variants( bb_section_variants() );
}

$sections->register();

$lawyers = $registry->get( 'lawyers' );
check( 'lawyers schema exposes a variant setting', isset( $lawyers['settings']['variant'] ) );
check( 'variant setting is a select', 'select' === ( $lawyers['settings']['variant']['type'] ?? '' ) );
check( 'variant default is default', 'default' === ( $lawyers['settings']['variant']['default'] ?? '' ) );
check( 'variant options include list', isset( $lawyers['settings']['variant']['options']['list'] ) );
check( 'variant label is translated (not a raw slug)', 'list' !== ( $lawyers['settings']['variant']['options']['list'] ?? 'list' ) );

$services = $registry->get( 'legal_services' );
check( 'services schema exposes a variant setting', isset( $services['settings']['variant'] ) );

$pa = $registry->get( 'practice_areas' );
check( 'practice_areas schema exposes a variant setting', isset( $pa['settings']['variant'] ) );

$faq = $registry->get( 'faq' );
check( 'faq schema has NO variant setting (single layout)', ! isset( $faq['settings']['variant'] ) );

$tst = $registry->get( 'testimonials' );
check( 'testimonials schema has NO variant setting (single layout)', ! isset( $tst['settings']['variant'] ) );

/* Other settings survive (no accidental schema damage). */
check( 'lawyers keeps columns setting', isset( $lawyers['settings']['columns'] ) );
check( 'lawyers keeps practice_area setting', isset( $lawyers['settings']['practice_area'] ) );
check( 'lawyers keeps order setting', isset( $lawyers['settings']['order'] ) );

restore_current_blog();
echo 'DONE' . PHP_EOL;