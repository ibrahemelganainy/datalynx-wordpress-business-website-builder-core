<?php
/**
 * Structural check: billing details are collected ONLY on the dedicated
 * /paymob-billing/ page — never inline on the consultation / booking form.
 *
 * The inline duplicate was an unwanted regression; this test locks the
 * intended architecture in place:
 *
 *   form -> Continue to payment -> dedicated /paymob-billing/ page (token)
 *        -> billing details -> gateway checkout
 */
$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/';
$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;

    if ( $cond ) {
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

$consult = (string) file_get_contents( $root . 'templates/consultation-form.php' );
$booking = (string) file_get_contents( $root . 'templates/booking-form.php' );
$partial = (string) file_get_contents( $root . 'templates/partials/billing-form.php' );
$billing = (string) file_get_contents( $root . 'packs/LawFirm/Frontend/BillingPage.php' );

/* The inline duplicate must NOT be present anywhere in the forms. */
chk( 'consultation has NO inline billing partial', false === strpos( $consult, 'partials/billing-form.php' ) );
chk( 'booking has NO inline billing partial', false === strpos( $booking, 'partials/billing-form.php' ) );

/* The dedicated billing page owns the billing fields. */
$fields = array( 'bb_billing_first_name', 'bb_billing_last_name', 'bb_billing_email', 'bb_billing_phone' );

foreach ( $fields as $field ) {
    chk( "dedicated page defines $field", false !== strpos( $billing, $field ) );
}

chk( 'billing page registers the /paymob-billing/ route', false !== strpos( $billing, 'paymob-billing' ) );
chk( 'billing page verifies the one-time token', false !== strpos( $billing, 'hash_equals' ) );
chk( 'billing page re-reads amount from the saved snapshot', false !== strpos( $billing, 'payment_context' ) );

/* The reusable partial still defines the four fields (used by the page). */
chk( 'partial defines the four billing fields', false !== strpos( $partial, 'bb_billing_first_name' ) && false !== strpos( $partial, 'bb_billing_phone' ) );

$js = (string) file_get_contents( $root . 'assets/js/frontend/payment-billing.js' );
chk( 'js reads the gateway radios', false !== strpos( $js, 'bb_payment_gateway' ) );
chk( 'js validates email', false !== strpos( $js, 'isValidEmail' ) );
chk( 'js validates phone digits', false !== strpos( $js, 'isValidPhone' ) );
chk( 'js prevents submit on invalid', false !== strpos( $js, 'preventDefault' ) );
chk( 'js manages the required attribute', false !== strpos( $js, "'required'" ) );

$css = (string) file_get_contents( $root . 'assets/css/frontend/billing-form.css' );
chk( 'css has responsive rule', false !== strpos( $css, '@media (max-width: 560px)' ) );

$plugin = (string) file_get_contents( $root . 'includes/Core/Plugin.php' );
chk( 'plugin enqueues payment-billing.js', false !== strpos( $plugin, 'payment-billing.js' ) );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
