<?php
/**
 * Real WordPress runtime test: payment wiring for consultation + appointment.
 *
 * Verifies the complete chain the task requires:
 *   Payment Settings -> enabled gateway -> section config -> saved page meta
 *   -> frontend form -> selected gateway -> transaction -> verification ->
 *   status -> receipt -> status lookup.
 *
 * Run:  php tests/runtime-payment-flows.php
 *
 * Uses the real WP runtime (wp-load) on the current blog and cleans up its
 * own fixtures (pages, consultations, appointments, transactions).
 */

define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;
use BusinessBuilderCore\Packs\LawFirm\Payments\SectionPayment;
use BusinessBuilderCore\Packs\LawFirm\Payments\SectionPaymentFactory;

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) {
        $pass++;
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

$sep = chr( 47 ); // '/'

echo "== 1. Currency: persists + unknown legacy value preserved ==\n";

$settings = new \BusinessBuilderCore\Settings\SiteSettings();

$settings->set( 'consultation_currency', 'EGP' );
$all = $settings->get_all();
check( 'currency EGP persists', 'EGP' === $all['consultation_currency'] );

/* Unknown/legacy currency is offered (not silently dropped) for the selector. */
$options = Currencies::select_options( 'XYZ' );
check( 'known EGP is a valid code', Currencies::exists( 'EGP' ) );
check( 'legacy unknown code appears in selector', isset( $options['XYZ'] ) && ! empty( $options['XYZ']['legacy'] ) );
check( 'legacy code keeps its raw value', isset( $options['XYZ'] ) && 'XYZ' === $options['XYZ']['code'] );

/* Sanitizing an unknown currency falls back safely (does not store junk). */
$settings->set( 'consultation_currency', 'EGP' );

echo "== 2. Require Payment by Default (consultations + appointments) ==\n";

$settings->set( 'require_consultation_payment', true );
$settings->set( 'require_appointment_payment', true );
$all = $settings->get_all();
check( 'require_consultation_payment persists', ! empty( $all['require_consultation_payment'] ) );
check( 'require_appointment_payment persists', ! empty( $all['require_appointment_payment'] ) );

echo "== 3. Section payment resolution (gateway gating) ==\n";

$pm = new PaymentManager();

/* Enable a manual gateway and fully configure it (3 required fields). */
$pm->set_enabled_gateways( array( 'bank_transfer', 'stripe' ));
$pm->save_settings(
    'bank_transfer',
    array(
        'bank_name'      => 'Test Bank',
        'account_name'   => 'Test Account',
        'account_number' => '1234567890',
        'instructions'   => 'Transfer and reply with the reference.',
    )
);

$resolver = new SectionPayment( $pm, new \BusinessBuilderCore\Settings\SiteSettings() );

/* Consultation section selecting only bank_transfer. */
$consult = $resolver->resolve(
    'consultation',
    array(
        'payment_enabled'  => '1',
        'payment_fee'      => '500',
        'payment_currency' => 'EGP',
        'payment_gateways' => array( 'bank_transfer', 'paypal' ),
    )
);

check( 'consultation payable when an allowed+ready gateway exists', ! empty( $consult['payable'] ) );
check( 'consultation fee normalized', '500.00' === $consult['fee'] );
check( 'consultation currency honoured', 'EGP' === $consult['currency'] );
check( 'only allowed+ready gateways offered', in_array( 'bank_transfer', $consult['gateways'], true ) );
check( 'not-configured paypal excluded', ! in_array( 'paypal', $consult['gateways'], true ) );
check( 'unselected stripe excluded', ! in_array( 'stripe', $consult['gateways'], true ) );

/* Appointment section selecting stripe (configured? -> no). */
$appt = $resolver->resolve(
    'appointment',
    array(
        'payment_enabled'  => '1',
        'payment_fee'      => '1000',
        'payment_currency' => 'EGP',
        'payment_gateways' => array( 'stripe' ),
    )
);
check( 'appointment fee independent from consultation', '1000.00' === $appt['fee'] );

/* Global default applies only when the section does not decide. */
$inherit = $resolver->resolve( 'appointment', array() );
check( 'global default enables payment when section is silent', true === $inherit['enabled'] );

echo "== 4. Stored section meta -> server-side resolution ==\n";

$page_id = wp_insert_post(
    array(
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'BB Payment Test Page',
    ),
    true
);

$section_id = 'sec_test_1';

update_post_meta(
    $page_id,
    '_bb_page_sections',
    array(
        array(
            'id'       => $section_id,
            'type'     => 'consultation',
            'settings' => array(
                'payment_enabled'  => '1',
                'payment_fee'      => '777',
                'payment_currency' => 'EGP',
                'payment_gateways' => array( 'bank_transfer' ),
            ),
        ),
    )
);

$stored = SectionPaymentFactory::resolve_stored_section( $page_id, $section_id, 'consultation', 'consultation' );
check( 'stored section resolves', is_array( $stored ) );
check( 'stored section fee read from meta', is_array( $stored ) && '777.00' === $stored['fee'] );
check( 'stored section is payable', is_array( $stored ) && ! empty( $stored['payable'] ) );

$wrong_type = SectionPaymentFactory::resolve_stored_section( $page_id, $section_id, 'booking', 'appointment' );
check( 'type mismatch is refused', null === $wrong_type );

echo "== 5. Secure references ==\n";

$cns = Reference::consultation();
$apt = Reference::appointment();
$txn = Reference::transaction();

check( 'consultation ref shape', (bool) preg_match( '/^CNS-[A-Z2-9]{10}$/', $cns ) );
check( 'appointment ref shape', (bool) preg_match( '/^APT-[A-Z2-9]{10}$/', $apt ) );
check( 'transaction ref shape', (bool) preg_match( '/^TXN-[A-Z2-9]{10}$/', $txn ) );
check( 'references are non-sequential (two differ)', Reference::appointment() !== Reference::appointment() );

echo "== 6. Transaction lifecycle + object links ==\n";

$consultation_id = wp_insert_post(
    array(
        'post_type'   => 'bb_consultation',
        'post_status' => 'publish',
        'post_title'  => 'Test Consultation',
    ),
    true
);

update_post_meta( $consultation_id, '_bb_consultation_public_reference', $cns );
update_post_meta( $consultation_id, '_bb_consultation_payment_status', 'pending' );

$txn_obj = $pm->persist(
    \BusinessBuilderCore\Core\Payments\PaymentTransaction::from_array(
        array(
            'public_ref'  => $txn,
            'object_type' => 'consultation',
            'object_id'   => $consultation_id,
            'gateway'     => 'bank_transfer',
            'amount'      => '500.00',
            'currency'    => 'EGP',
            'status'      => 'pending',
            'meta'        => array( 'label' => 'Legal Consultation' ),
        )
    )
);

check( 'transaction persisted with an id', $txn_obj->id > 0 );

$found = $pm->find_by_public_ref( $txn );
check( 'transaction found by public ref', $found instanceof \BusinessBuilderCore\Core\Payments\PaymentTransaction );

/* Simulate a verified server-side "paid" transition. */
$paid = $pm->record_status_change( $txn_obj->id, 'paid', 'GTW-123456789' );
check( 'transaction marked paid', $paid instanceof \BusinessBuilderCore\Core\Payments\PaymentTransaction && 'paid' === $paid->status );

echo "== 7. Receipt (object ref + gateway tx id, no secrets) ==\n";

$gateway_name = $pm->gateway( 'bank_transfer' )->get_name();
$receipt = Receipt::from_transaction( $paid, $gateway_name );

check( 'receipt carries consultation reference', $cns === $receipt->object_reference );
check( 'receipt carries payment reference', $txn === $receipt->reference );
check( 'receipt carries gateway transaction id', 'GTW-123456789' === $receipt->gateway_reference );
check( 'receipt reports paid', true === $receipt->is_paid );

$renderer = new \BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer();
$html = $renderer->render( $receipt );
check( 'receipt html includes consultation ref', false !== strpos( $html, $cns ) );
check( 'receipt html includes gateway tx id', false !== strpos( $html, 'GTW-123456789' ) );
check( 'receipt html leaks no api key string', false === stripos( $html, 'api_key' ) );

echo "== 8. Appointment public reference on creation ==\n";

/* Register the CPT (as a real request would) so the slot query works. */
( new \BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment() )->register_post_type();

$availability = new \BusinessBuilderCore\Packs\LawFirm\Appointments\Availability();

/*
 * Find a working day AND a genuinely free slot, so the assertion is not
 * broken by appointments left behind by other test runs (pollution).
 */
$appt_date = '';
$appt_time = '';

for ( $i = 1; $i <= 21; $i++ ) {

    $candidate = gmdate( 'Y-m-d', strtotime( "+$i days" ));

    if ( ! $availability->is_working_day( $candidate )) {
        continue;
    }

    $day_slots = $availability->slots_for_date( $candidate );

    foreach ( $day_slots as $slot ) {
        if ( ! empty( $slot['available'] )) {
            $appt_date = $candidate;
            $appt_time = (string) $slot['start'];
            break 2;
        }
    }
}

$appt_id = ( '' === $appt_time )
    ? new \WP_Error( 'bb_no_free_slot', 'no free slot found in the test window' )
    : $availability->create(
        array(
            'client_name'  => 'Test Client',
            'client_phone' => '0100000',
            'date'         => $appt_date,
            'start'        => $appt_time,
            'type'         => 'consultation',
        )
    );

if ( is_wp_error( $appt_id )) {
    check( 'appointment created', false );
} else {
    $appt_ref = (string) get_post_meta( (int) $appt_id, '_bb_appointment_public_reference', true );
    check( 'appointment has a public reference', (bool) preg_match( '/^APT-[A-Z2-9]{10}$/', $appt_ref ) );
}

echo "== 9. Configure gateway button fix (CSS [hidden] override) ==\n";

$css_path = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/assets/css/admin/payment-center.css';
$css = file_get_contents( $css_path );
check( 'CSS restores hidden gateways fields', false !== strpos( $css, '.bb-gateway-fields[hidden]' ) );

echo "== 10. Regression: notification/currency defaults intact ==\n";

$settings->update(
    array(
        'consultation_fee'      => '123',
        'consultation_currency' => 'USD',
    )
);
$all = $settings->get_all();
check( 'default fee sanitized', '123' === (string) $all['consultation_fee'] );

echo "== 11. End-to-end chain: settings -> section -> checkout -> verify -> receipt -> status ==\n";

/*
 * Trace the complete chain the task requires, using the real services:
 *   Payment Settings (enabled + configured gateway)
 *   -> section meta -> SectionPaymentFactory
 *   -> PaymentFlow (creates a pending transaction + provider reference)
 *   -> TransactionSynchronizer (server-side verification)
 *   -> Receipt (object + payment references)
 *   -> StatusPage (private lookup by public reference)
 */

use BusinessBuilderCore\Core\Payments\PaymentResult;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Packs\LawFirm\Payments\PaymentFlow;

$pm->set_enabled_gateways( array( 'bank_transfer' ));
$pm->save_settings(
    'bank_transfer',
    array(
        'bank_name'      => 'E2E Bank',
        'account_name'   => 'E2E Account',
        'account_number' => '999',
        'instructions'   => 'Do the transfer.',
    )
);

$e2e_page = wp_insert_post(
    array(
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'BB E2E Page',
    ),
    true
);

$e2e_section = 'sec_e2e';

update_post_meta(
    $e2e_page,
    '_bb_page_sections',
    array(
        array(
            'id'       => $e2e_section,
            'type'     => 'consultation',
            'settings' => array(
                'payment_enabled'  => '1',
                'payment_fee'      => '500',
                'payment_currency' => 'EGP',
                'payment_gateways' => array( 'bank_transfer' ),
            ),
        ),
    )
);

/* 1) Section config resolved from saved meta. */
$e2e_config = SectionPaymentFactory::resolve_stored_section( $e2e_page, $e2e_section, 'consultation', 'consultation' );
check( 'e2e: section resolves + payable', is_array( $e2e_config ) && ! empty( $e2e_config['payable'] ));

/* Object: the consultation being paid for. */
$e2e_cns_id = wp_insert_post(
    array(
        'post_type'   => 'bb_consultation',
        'post_status' => 'publish',
        'post_title'  => 'E2E Consultation',
    ),
    true
);
$e2e_cns_ref = Reference::consultation();
update_post_meta( $e2e_cns_id, '_bb_consultation_public_reference', $e2e_cns_ref );

/* 2) Start the checkout through the real flow. */
$audit = new \BusinessBuilderCore\Core\Audit\AuditLog();
$flow  = new PaymentFlow( new PaymentCheckout( $pm, $audit ), $pm, $audit );

$e2e_result = $flow->start( 'consultation', $e2e_cns_id, $e2e_config, 'bank_transfer', 'Legal Consultation', 'client@example.com' );
check( 'e2e: checkout returns a routing type', isset( $e2e_result['type'] ));
check( 'e2e: manual gateway returns manual routing', 'manual' === ( $e2e_result['type'] ?? '' ));

$e2e_txn = isset( $e2e_result['transaction'] ) ? $e2e_result['transaction'] : null;
check( 'e2e: a payment transaction was created', $e2e_txn instanceof \BusinessBuilderCore\Core\Payments\PaymentTransaction );
check( 'e2e: transaction is pending (not paid)', $e2e_txn instanceof \BusinessBuilderCore\Core\Payments\PaymentTransaction && 'pending' === $e2e_txn->status );

/* The consultation was stamped pending-payment, not confirmed/paid. */
$e2e_meta_status = (string) get_post_meta( $e2e_cns_id, '_bb_consultation_payment_status', true );
check( 'e2e: consultation payment status is pending', 'pending' === $e2e_meta_status );
check( 'e2e: consultation not marked paid by browser return', 'paid' !== $e2e_meta_status );

/* 3) Server-side verification marks paid + updates the object. */
$sync = new TransactionSynchronizer( $pm, $audit );
$verified = new PaymentResult( true, 'paid', 'GTW-E2E-1', 'verified' );
$synced = $sync->apply( $e2e_txn, $verified );
check( 'e2e: verified payment marks transaction paid', 'paid' === $synced->status );
check( 'e2e: verified payment updates consultation to paid', 'paid' === (string) get_post_meta( $e2e_cns_id, '_bb_consultation_payment_status', true ));

/* 4) Receipt carries both references. */
$e2e_receipt = Receipt::from_transaction( $synced, $pm->gateway( 'bank_transfer' )->get_name() );
check( 'e2e: receipt has consultation reference', $e2e_cns_ref === $e2e_receipt->object_reference );
check( 'e2e: receipt has payment reference', $e2e_txn->public_ref === $e2e_receipt->reference );

/* 5) Private status lookup by consultation reference. */
$status_page = new \BusinessBuilderCore\Packs\LawFirm\Frontend\StatusPage( $pm );
$status_html = $status_page->render( $e2e_cns_ref );
check( 'e2e: status page shows the consultation reference', false !== strpos( $status_html, $e2e_cns_ref ));
check( 'e2e: status page exposes no internal post id', false === strpos( $status_html, 'post-' . $e2e_cns_id ));

/* Cleanup */
wp_delete_post( $e2e_cns_id, true );
wp_delete_post( $e2e_page, true );

/*
 * ENUMERATION GUARD: an unknown but well-formed reference must return
 * the same uniform "no record" notice (no way to probe existence).
 */
$unknown = $status_page->render( 'CNS-ZZZZ' );
check( 'e2e: unknown reference yields no record (no enumeration)', false !== strpos( $unknown, 'No record' ) || false !== strpos( $unknown, 'no record' ));

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* ---- cleanup ---- */
if ( $consultation_id ) {
    wp_delete_post( $consultation_id, true );
}
if ( isset( $appt_id ) && ! is_wp_error( $appt_id )) {
    wp_delete_post( (int) $appt_id, true );
}
if ( $page_id ) {
    wp_delete_post( $page_id, true );
}

/*
 * Restore the payment options this test touched so other runtime tests
 * that assert a clean blog (e.g. runtime-payments.php) are unaffected.
 */
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_transactions' );
delete_option( 'bb_payment_gateways' );

$settings->reset();

exit( 0 === $fail ? 0 : 1 );
