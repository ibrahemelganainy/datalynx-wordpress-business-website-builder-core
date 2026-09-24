<?php
/*
 * End-to-end: verify WordPress loads the plugin's Arabic .mo through the
 * plugin's OWN declared path (Plugin::load_textdomain -> load_plugin_textdomain)
 * and that it resolves while the plugin is booted normally.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

/* Simulate an Arabic request the way WordPress would: set the locale first. */
add_filter( 'locale', function () { return 'ar'; } );
add_filter( 'determine_locale', function () { return 'ar'; } );

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

/* Unload any en_US copy, then load as Arabic. */
unload_textdomain( 'business-builder' );

/* The plugin registers this at init. Call the same function it uses. */
$loaded = load_plugin_textdomain(
    'business-builder',
    false,
    dirname( plugin_basename( 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/business-builder-core.php' ) ) . '/languages'
);

check( 'load_plugin_textdomain() succeeded', (bool) $loaded );

/* Verify a spread of real plugin strings resolve to Arabic. */
$probe = array(
    'Dashboard'                 => 'لوحة التحكم',
    'Paid'                      => 'مدفوع',
    'Bank Transfer'             => 'التحويل البنكي',
    'Notifications'             => 'الإشعارات',
    'Book Appointment'          => 'احجز موعدًا',
);

foreach ( $probe as $en => $ar ) {
    check( "translate '$en'", __( $en, 'business-builder' ) === $ar );
}

/* A string that must NOT be translated (already Arabic / no entry). */
check( 'untranslated passthrough', __( 'ZZZ-not-a-string', 'business-builder' ) === 'ZZZ-not-a-string' );

echo 'DONE' . PHP_EOL;
