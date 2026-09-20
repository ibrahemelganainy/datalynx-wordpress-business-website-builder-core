<?php
/**
 * Runtime test: Paymob billing_data is valid (no "NA" placeholders that
 * Paymob rejects). Verifies name/phone/email come from the transaction and
 * fall back to VALID values (digits-only phone, real email, ISO country).
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\Gateways\PaymobGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;

$fail = 0;

function expect( string $label, bool $cond, int &$fail ): void {
    if ( $cond ) {
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

$gw   = new PaymobGateway();
$meth = new ReflectionMethod( $gw, 'billing_data' );
$meth->setAccessible( true );

/* Case 1: a real customer. */
$txn = PaymentTransaction::from_array(
    array(
        'meta' => array(
            'name'  => 'Amina Youssef',
            'phone' => '+20 109 988 7766',
            'email' => 'amina@example.com',
        ),
    )
);

$b = $meth->invoke( $gw, $txn );
echo 'real: ' . wp_json_encode( $b ) . "\n";
expect( 'first_name from customer', 'Amina' === $b['first_name'], $fail );
expect( 'last_name from customer', 'Youssef' === $b['last_name'], $fail );
expect( 'phone is digits only', '201099887766' === $b['phone_number'], $fail );
expect( 'email preserved', 'amina@example.com' === $b['email'], $fail );
expect( 'country is ISO-2', 'EG' === $b['country'], $fail );

/* Case 2: empty data must fall back to VALID values, never "NA" email/phone. */
$txn2 = PaymentTransaction::from_array( array( 'meta' => array() ) );
$b2   = $meth->invoke( $gw, $txn2 );
echo 'empty: ' . wp_json_encode( $b2 ) . "\n";
expect( 'fallback email is valid format', false !== is_email( $b2['email'] ), $fail );
expect( 'fallback phone is digits only', (bool) preg_match( '/^[0-9]+$/', $b2['phone_number'] ), $fail );
expect( 'fallback phone is not "NA"', 'NA' !== $b2['phone_number'], $fail );
expect( 'fallback email is not "NA"', 'NA' !== $b2['email'], $fail );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
