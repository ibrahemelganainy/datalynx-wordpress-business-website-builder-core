<?php
/**
 * Runtime test: dedicated Paymob billing page (redirect + token + flow).
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Frontend\BillingPage;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;

$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;
    if ( $cond ) { echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* Register the consultation CPT for the lookup query. */
( new \BusinessBuilderCore\Packs\LawFirm\PostTypes\Consultation() )->register_post_type();

/* 1) URL builder. */
$url = BillingPage::url( 'CNS-ABCDEF1234', 'tok123' );
echo "url: $url\n";
chk( 'url contains the reference', false !== strpos( $url, 'CNS-ABCDEF1234' ) );
chk( 'url carries the token', false !== strpos( $url, 'token=tok123' ) );
chk( 'url is absolute', 0 === strpos( $url, 'http' ) );

/* 2) store_pending writes a snapshot + a token. */
$cid = wp_insert_post(
    array(
        'post_type'   => 'bb_consultation',
        'post_status' => 'publish',
        'post_title'  => 'Billing Page Test',
    ),
    true
);

$ref = 'CNS-BILLTEST01';
update_post_meta( $cid, ConsultationMeta::key( 'public_reference' ), $ref );

$context = array(
    'gateway'  => 'paymob',
    'fee'      => '500.00',
    'currency' => 'EGP',
    'gateways' => array( 'paymob' ),
);

$token = BillingPage::store_pending( 'consultation', $cid, $context );
echo "token length: " . strlen( $token ) . "\n";

chk( 'token is a 40-char random string', 40 === strlen( $token ) );
chk( 'snapshot stored', is_array( get_post_meta( $cid, '_bb_consultation_payment_context', true ) ) );
chk( 'token stored', '' !== (string) get_post_meta( $cid, '_bb_consultation_payment_token', true ));

/* 3) resolve_order: correct token resolves; wrong/empty token does not. */
$page = new BillingPage();
$resolve = new ReflectionMethod( $page, 'resolve_order' );
$resolve->setAccessible( true );

$ok = $resolve->invoke( $page, $ref, $token );
chk( 'correct token resolves the order', is_array( $ok ) );
chk( 'resolved order carries the EGP amount', is_array( $ok ) && '500.00' === $ok['context']['fee'] );
chk( 'resolved order carries the currency', is_array( $ok ) && 'EGP' === $ok['context']['currency'] );

chk( 'wrong token is refused', null === $resolve->invoke( $page, $ref, 'wrong-token' ) );
chk( 'empty token is refused', null === $resolve->invoke( $page, $ref, '' ) );
chk( 'unknown reference is refused', null === $resolve->invoke( $page, 'CNS-NOPE000', $token ) );

/* 4) The handlers redirect to the dedicated page for billing gateways. */
$consult_src = (string) file_get_contents( 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/packs/LawFirm/Frontend/ConsultationForm.php' );
$booking_src = (string) file_get_contents( 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/packs/LawFirm/Appointments/BookingForm.php' );

chk( 'consultation redirects to BillingPage::url', false !== strpos( $consult_src, 'BillingPage::url' ) );
chk( 'booking redirects to BillingPage::url', false !== strpos( $booking_src, 'BillingPage::url' ) );
chk( 'consultation stores the snapshot', false !== strpos( $consult_src, 'BillingPage::store_pending' ) );
chk( 'booking stores the snapshot', false !== strpos( $booking_src, 'BillingPage::store_pending' ) );

/* 5) The dedicated page no longer renders inline inside the section. */
$sections_src = (string) file_get_contents( 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/packs/LawFirm/Sections/LawFirmSections.php' );
chk( 'no inline payment-step render in sections', false === strpos( $sections_src, 'render_payment_step' ) );
chk( 'no inline bb_pay context in sections', false === strpos( $sections_src, 'payment_step_context' ) );

/* 6) Rewrite route + query var registered. */
$page2 = new BillingPage();
$vars  = $page2->add_query_var( array( 'foo' ) );
chk( 'query var registered', in_array( BillingPage::QUERY_VAR, $vars, true ) );


/* 7) Robust route detection (query var / raw query / REQUEST_URI). */
$detect = new ReflectionMethod( $page2, 'detect_reference' );
$detect->setAccessible( true );

$_GET = array( BillingPage::QUERY_VAR => 'CNS-VIAREWRITE' );
$got  = $detect->invoke( $page2 );
chk( 'detects via query var', 'CNS-VIAREWRITE' === $got );

$_GET = array( 'bb_billing_ref' => 'CNS-VIAQUERY' );
$got  = $detect->invoke( $page2 );
chk( 'detects via raw query string', 'CNS-VIAQUERY' === $got );

$_GET = array();
$_SERVER['REQUEST_URI'] = '/paymob-billing/CNS-VIAURI/?token=abc';
$got = $detect->invoke( $page2 );
chk( 'detects via REQUEST_URI path', 'CNS-VIAURI' === $got );

$_SERVER['REQUEST_URI'] = '/some/other/page/';
$got = $detect->invoke( $page2 );
chk( 'ignores unrelated paths', '' === $got );

unset( $_SERVER['REQUEST_URI'] );
$_GET = array();
wp_delete_post( $cid, true );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
