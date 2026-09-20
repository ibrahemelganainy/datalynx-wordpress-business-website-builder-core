<?php
/**
 * Runtime test: separate Paymob payment step + currency guard.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections;
use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Core\Payments\Gateways\PaymobGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;

$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;
    if ( $cond ) { echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* 1) Paymob currency guard: non-EGP must be refused with a clear reason. */
$gw  = new PaymobGateway();
$txn = PaymentTransaction::from_array(
    array(
        'public_ref' => 'TXN-CURRTEST01',
        'gateway'    => 'paymob',
        'amount'     => '500.00',
        'currency'   => 'USD',
        'status'     => 'pending',
        'meta'       => array(),
    )
);

$res  = $gw->create_payment( $txn );
$type = isset( $res['type'] ) ? (string) $res['type'] : '';
echo 'non-EGP result: ' . wp_json_encode( $res ) . "\n";
chk( 'non-EGP currency is refused (unavailable, not a redirect)', 'unavailable' === $type );
chk( 'non-EGP reason mentions the currency/config', false !== stripos( (string) ( $res['message'] ?? '' ), 'currency' ) );

/*
 * 2) CURRENT ARCHITECTURE: billing is collected ONLY on the dedicated
 * /paymob-billing/ page (BillingPage) - the inline payment_step_context
 * method and templates/partials/payment-step.php were removed. Assert
 * they stay gone, and that the dedicated page owns the flow.
 */
$registry = new SectionRegistry();
$sections = new LawFirmSections( $registry );

chk( 'inline payment_step_context method is gone', ! method_exists( $sections, 'payment_step_context' ) );

$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/';
chk( 'inline payment-step partial is gone', ! file_exists( $root . 'templates/partials/payment-step.php' ) );

/* 3) The dedicated billing page owns the token + amount snapshot. */
$billing_src = (string) file_get_contents( $root . 'packs/LawFirm/Frontend/BillingPage.php' );
chk( 'dedicated billing page registers /paymob-billing/', false !== strpos( $billing_src, 'paymob-billing' ) );
chk( 'dedicated billing page verifies a token', false !== strpos( $billing_src, 'hash_equals' ) );
chk( 'dedicated billing page reads the saved snapshot', false !== strpos( $billing_src, 'payment_context' ) );

/* 4) Both handlers route billing gateways to the dedicated step. */
$consult = (string) file_get_contents( $root . 'packs/LawFirm/Frontend/ConsultationForm.php' );
$booking = (string) file_get_contents( $root . 'packs/LawFirm/Appointments/BookingForm.php' );

chk( 'consultation redirects to the dedicated step', false !== strpos( $consult, 'redirect_to_payment_step' ) );
chk( 'booking redirects to the dedicated step', false !== strpos( $booking, 'redirect_to_payment_step' ) );
chk( 'consultation gates on bb_pay_step', false !== strpos( $consult, "'billing' !== \$billing_step" ) );
chk( 'booking gates on bb_pay_step', false !== strpos( $booking, "'billing' !== \$billing_step" ) );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
