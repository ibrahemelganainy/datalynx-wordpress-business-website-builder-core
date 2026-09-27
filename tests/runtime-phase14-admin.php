<?php
/* Phase 14: verify the Customizer registers the preset + design controls. */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

switch_to_blog( 2 );

/* Load the theme's functions so bb_theme_customize_register() is defined. */
require_once WP_CONTENT_DIR . '/themes/business-builder/functions.php';

require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
$wp_customize = new WP_Customize_Manager();

/* The manager fires customize_register only in the real customize context;
 * call the registrar directly so we can assert the registrations. */
bb_theme_customize_register( $wp_customize );

check( 'panel registered', (bool) $wp_customize->get_panel( 'bb_theme_panel' ) );
check( 'preset section registered', (bool) $wp_customize->get_section( 'bb_theme_settings' ) );
check( 'colors section registered', (bool) $wp_customize->get_section( 'bb_theme_colors' ) );
check( 'typography section registered', (bool) $wp_customize->get_section( 'bb_theme_typography' ) );
check( 'layout section registered', (bool) $wp_customize->get_section( 'bb_theme_layout' ) );
check( 'radius section registered', (bool) $wp_customize->get_section( 'bb_theme_radius' ) );
check( 'shell section registered', (bool) $wp_customize->get_section( 'bb_theme_shell' ) );

check( 'preset control registered', (bool) $wp_customize->get_control( 'bb_theme_preset' ) );
check( 'color control registered', (bool) $wp_customize->get_control( 'bb_design_color_primary' ) );
check( 'font control registered', (bool) $wp_customize->get_control( 'bb_design_font_body' ) );
check( 'container control registered', (bool) $wp_customize->get_control( 'bb_design_container_width' ) );
check( 'radius control registered', (bool) $wp_customize->get_control( 'bb_design_radius_lg' ) );
check( 'shell control registered', (bool) $wp_customize->get_control( 'bb_design_footer_bg' ) );

/* Every schema control has a matching setting. */
$missing = 0;
foreach ( bb_theme_design_schema() as $c ) {
    if ( ! $wp_customize->get_setting( bb_theme_design_mod_name( (string) $c['key'] ) ) ) { $missing++; }
}
check( 'every schema control has a setting (' . $missing . ' missing)', 0 === $missing );

restore_current_blog();
echo 'DONE' . PHP_EOL;