<?php
/*
 * Verify the compiled business-builder-ar.mo actually translates at runtime.
 * Loads the real WordPress + plugin, forces the 'ar' locale, and asserts that
 * a spread of strings (singular, contextual, plural) resolve to Arabic.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

/* Force Arabic for this request. */
add_filter( 'determine_locale', function () { return 'ar'; } );
switch_to_locale( 'ar' );

/* Re-load the plugin text domain under 'ar' (already done by WP if locale=ar). */
$loaded = load_textdomain( 'business-builder', WP_LANG_DIR . '/plugins/business-builder-ar.mo' );
if ( ! $loaded ) {
    // Fall back to the plugin's own languages dir.
    $loaded = load_textdomain(
        'business-builder',
        ABSPATH . 'wp-content/plugins/business-builder-core/languages/business-builder-ar.mo'
    );
}
check( 'text domain loaded from .mo', (bool) $loaded );

/* Singular. */
check( 'singular __() translates',  __( 'Total Lawyers', 'business-builder' ) === 'إجمالي المحامين' );
check( 'singular __() translates (2)', __( 'Manual Payments', 'business-builder' ) === 'المدفوعات اليدوية' );
check( 'message string translates', __( 'Name is required.', 'business-builder' ) === 'الاسم مطلوب.' );

/* Contextual _x(). */
$pt = _x( 'Payments', 'post type general name', 'business-builder' );
check( 'contextual _x() translates', $pt === 'المدفوعات' );

/* Plural _n() — Arabic CLDR 6 forms. */
$one = _n( '%d section', '%d sections', 1, 'business-builder' );
$two = _n( '%d section', '%d sections', 2, 'business-builder' );
$few = _n( '%d section', '%d sections', 5, 'business-builder' );
$many = _n( '%d section', '%d sections', 15, 'business-builder' );
check( 'plural n=1 (singular)', strpos( $one, 'قسم واحد' ) !== false );
check( 'plural n=2 (dual)',    strpos( $two, 'قسمان' ) !== false );
check( 'plural n=5 (few)',     strpos( $few, 'أقسام' ) !== false );
check( 'plural n=15 (many)',   strpos( $many, 'قسمًا' ) !== false );

/* The %d placeholder must be preserved for the caller's sprintf(). */
check( 'plural form keeps %d placeholder', strpos( $many, '%d' ) !== false );

restore_previous_locale();
echo 'DONE' . PHP_EOL;
