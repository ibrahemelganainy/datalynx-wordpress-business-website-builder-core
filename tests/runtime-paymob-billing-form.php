<?php
/**
 * Runtime test: dynamic billing collected ONLY for gateways that need it.
 *
 * Verifies ConsultationForm::collect_billing() and BookingForm::collect_billing():
 *   - Paymob => returns a valid billing array (first/last/email/phone, country EG)
 *   - any other gateway => returns an EMPTY array (flow unchanged)
 *   - values are sanitized; phone is digits-only in the gateway output.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Frontend\ConsultationForm;
use BusinessBuilderCore\Packs\LawFirm\Appointments\BookingForm;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;

$fail = 0;

function expect( string $label, bool $cond, int &$fail ): void {
    if ( $cond ) {
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

/* Simulate a posted billing block. */
$_POST['bb_billing_first_name'] = 'Amina';
$_POST['bb_billing_last_name']  = 'Youssef';
$_POST['bb_billing_email']      = 'amina@example.com';
$_POST['bb_billing_phone']      = '010 1234 5678';

$consult_form = new ConsultationForm();
$cf = new ReflectionMethod( $consult_form, 'collect_billing' );
$cf->setAccessible( true );

$billing = $cf->invoke( $consult_form, 'paymob', array() );
echo 'paymob billing: ' . wp_json_encode( $billing ) . "\n";
expect( 'paymob: first_name collected', 'Amina' === $billing['first_name'], $fail );
expect( 'paymob: last_name collected', 'Youssef' === $billing['last_name'], $fail );
expect( 'paymob: email collected', 'amina@example.com' === $billing['email'], $fail );
expect( 'paymob: phone collected', '' !== $billing['phone'], $fail );
expect( 'paymob: country defaults to EG', 'EG' === $billing['country'], $fail );

$other = $cf->invoke( $consult_form, 'stripe', array() );
expect( 'stripe: billing is empty (unaffected)', array() === $other, $fail );

$other2 = $cf->invoke( $consult_form, 'bank_transfer', array() );
expect( 'bank_transfer: billing is empty (unaffected)', array() === $other2, $fail );

/* BookingForm mirrors the same behaviour. */
$booking = new BookingForm( new NotificationManager(), new AuditLog(), new Availability() );
$bf = new ReflectionMethod( $booking, 'collect_billing' );
$bf->setAccessible( true );

$bb = $bf->invoke( $booking, 'paymob', array() );
expect( 'booking: paymob billing collected', 'Amina' === $bb['first_name'], $fail );
expect( 'booking: stripe billing empty', array() === $bf->invoke( $booking, 'stripe', array() ), $fail );

/* Paymob gateway turns the billing into a valid billing_data block. */
$gw   = new \BusinessBuilderCore\Core\Payments\Gateways\PaymobGateway();
$meth = new ReflectionMethod( $gw, 'billing_data' );
$meth->setAccessible( true );

$txn = \BusinessBuilderCore\Core\Payments\PaymentTransaction::from_array(
    array(
        'meta' => array(
            'first_name' => 'Amina',
            'last_name'  => 'Youssef',
            'email'      => 'amina@example.com',
            'phone'      => '010 1234 5678',
        ),
    )
);

$bd = $meth->invoke( $gw, $txn );
echo 'billing_data: ' . wp_json_encode( $bd ) . "\n";
expect( 'gateway: uses explicit first name', 'Amina' === $bd['first_name'], $fail );
expect( 'gateway: uses explicit last name', 'Youssef' === $bd['last_name'], $fail );
expect( 'gateway: phone digits only', (bool) preg_match( '/^[0-9]+$/', $bd['phone_number'] ), $fail );
expect( 'gateway: valid email', false !== is_email( $bd['email'] ), $fail );
expect( 'gateway: country EG', 'EG' === $bd['country'], $fail );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
