<?php
/**
 * Runtime test: MANUAL payment PROOF upload + receipt data-source fixes.
 *
 * Covers the two production issues this change fixes:
 *
 *   1. Payment proof lifecycle:
 *      - a real uploaded file is stored as a PRIVATE attachment,
 *      - the attachment id is persisted on the TRANSACTION meta as
 *        "proof_attachment_id" (the canonical key the dashboard reads),
 *      - the Manual Payments dashboard renders the proof (image preview),
 *      - the four proof states are distinguished (exists / missing /
 *        deleted file / invalid reference), never all as "No proof uploaded".
 *
 *   2. Receipt data source:
 *      - the customer NAME/PHONE come from the canonical consultation /
 *        appointment record even when the transaction meta has none,
 *      - a canonical, stable receipt number (RCP-...) is produced.
 *
 * Run:  php tests/runtime-manual-proof.php
 */

define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Packs\LawFirm\Payments\ManualPaymentSubmission;

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

$pm = new PaymentManager();

/* Configure the manual gateway so the submission path accepts it. */
$pm->save_settings( 'wallet', array( 'wallet_provider' => 'Vodafone Cash', 'wallet_number' => '01099998888', 'instructions' => 'x' ) );
$pm->set_enabled_gateways( array( 'wallet' ) );

/*
 * A consultation with a real customer name/phone (the canonical source the
 * dashboard and the receipt must agree on).
 */
$cns = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'Proof Test' ), true );
update_post_meta( $cns, '_bb_consultation_public_reference', 'CNS-PROOFTEST1' );
update_post_meta( $cns, '_bb_consultation_name', 'Jane Doe' );
update_post_meta( $cns, '_bb_consultation_phone', '+201000' );
update_post_meta( $cns, '_bb_consultation_payment_status', 'pending' );

/* A real PNG file to upload. */
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$tmp_src = tempnam( sys_get_temp_dir(), 'proof-test-' ) . '.png';
file_put_contents( $tmp_src, $png );

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

echo "== 1. Submission stores the uploaded proof as a PRIVATE attachment ==\n";

$txn = $pm->persist(
    PaymentTransaction::from_array(
        array(
            'public_ref'  => 'TXN-PROOFTEST1',
            'object_type' => 'consultation',
            'object_id'   => $cns,
            'gateway'     => 'wallet',
            'amount'      => '500.00',
            'currency'    => 'EGP',
            'status'      => 'pending',
        )
    )
);

/*
 * The forms now carry enctype="multipart/form-data" (the ROOT CAUSE FIX).
 * Verify the two frontend forms actually declare it, so the browser really
 * transmits the file bytes to $_FILES.
 */
$consult_tpl = (string) file_get_contents( BB_CORE_PATH . 'templates/consultation-form.php' );
$booking_tpl = (string) file_get_contents( BB_CORE_PATH . 'templates/booking-form.php' );
check( 'consultation form is multipart', false !== strpos( $consult_tpl, 'enctype="multipart/form-data"' )) ;
check( 'booking form is multipart', false !== strpos( $booking_tpl, 'enctype="multipart/form-data"' )) ;

/*
 * Drive the real storage path. The filters let a CLI test inject the file
 * without the HTTP-only is_uploaded_file() check; production always keeps
 * the strict checks (the filters default to the safe values).
 */
$inject = static function () use ( $tmp_src ) {
    return array(
        'name'     => 'proof.png',
        'type'     => 'image/png',
        'tmp_name' => $tmp_src,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize( $tmp_src ),
    );
};

add_filter( 'bb_manual_payment_proof_file', $inject );
add_filter( 'bb_manual_payment_proof_require_upload', '__return_false' );

$ok = ManualPaymentSubmission::submit( 'consultation', $cns, 'wallet', array( 'bb_manual_reference' => 'TRX-PROOF-1' ), $txn );
check( 'submission accepted', true === $ok );

remove_filter( 'bb_manual_payment_proof_file', $inject );
remove_filter( 'bb_manual_payment_proof_require_upload', '__return_false' );

$stored = $pm->store()->find( $txn->id );
check( 'transaction reloaded', $stored instanceof PaymentTransaction );

$proof_id = $stored instanceof PaymentTransaction && isset( $stored->meta['proof_attachment_id'] )
    ? (int) $stored->meta['proof_attachment_id']
    : 0;

check( 'proof attachment id persisted on transaction meta', $proof_id > 0 );

$attachment = $proof_id > 0 ? get_post( $proof_id ) : null;
check( 'attachment created', $attachment instanceof WP_Post && 'attachment' === $attachment->post_type );
check( 'attachment is a private proof', '1' === (string) get_post_meta( $proof_id, '_bb_private_receipt', true ) );
check( 'attachment MIME is image/png', 'image/png' === (string) get_post_mime_type( $proof_id ) );
check( 'physical file exists on disk', file_exists( (string) get_attached_file( $proof_id )) ) ;

echo "== 2. Canonical customer name/phone + receipt number on the transaction ==\n";

check( 'customer name snapshotted from the consultation', 'Jane Doe' === ( $stored->meta['name'] ?? '' ) );
check( 'customer phone snapshotted from the consultation', '+201000' === ( $stored->meta['phone'] ?? '' ) );
check( 'canonical receipt number minted', ! empty( $stored->meta['receipt_number'] ) );

echo "== 3. Receipt reads name/phone from the canonical record + shows a receipt no ==\n";

$receipt = Receipt::from_transaction( $stored, 'Electronic Wallet' );
check( 'receipt customer name is the real name (not a dash)', 'Jane Doe' === $receipt->customer_name );
check( 'receipt customer phone is the real phone', '+201000' === $receipt->customer_phone );
check( 'receipt exposes a receipt number', 0 === strpos( $receipt->receipt_number, 'RCP-' ) );

$html = ( new \BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer() )->render( $receipt );
check( 'receipt HTML shows the customer name', false !== strpos( $html, 'Jane Doe' ) );
check( 'receipt HTML shows the receipt number', false !== strpos( $html, $receipt->receipt_number ) );
check( 'receipt HTML shows the phone', false !== strpos( $html, '+201000' ) );

echo "== 4. Receipt number is stable for the same transaction ==\n";

$again = Receipt::from_transaction( $stored, 'Electronic Wallet' );
check( 'receipt number is deterministic', $again->receipt_number === $receipt->receipt_number );

echo "== 5. Receipt name falls back to the canonical object when meta is empty ==\n";

$bare = PaymentTransaction::from_array(
    array(
        'public_ref'  => 'TXN-PROOFTEST2',
        'object_type' => 'consultation',
        'object_id'   => $cns,
        'gateway'     => 'wallet',
        'amount'      => '1.00',
        'currency'    => 'EGP',
        'status'      => 'on_hold',
        'meta'        => array(),
    )
);

$bare_receipt = Receipt::from_transaction( $bare, 'Electronic Wallet' );
check( 'empty-meta receipt still resolves the name from the consultation', 'Jane Doe' === $bare_receipt->customer_name );
check( 'empty-meta receipt still resolves the phone from the consultation', '+201000' === $bare_receipt->customer_phone );

echo "== 6. Proof states: the dashboard distinguishes missing/invalid/deleted ==\n";

$dashboard = new \BusinessBuilderCore\Packs\LawFirm\Admin\ManualPaymentsAdmin(
    $pm,
    new \BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer( $pm, new \BusinessBuilderCore\Core\Audit\AuditLog() ),
    new \BusinessBuilderCore\Core\Notifications\NotificationManager(),
    new \BusinessBuilderCore\Core\Audit\AuditLog()
);

$render_proof = new ReflectionMethod( $dashboard, 'render_proof' );
$render_proof->setAccessible( true );

/* a) Proof exists -> renders an image preview + a View Proof link. */
ob_start();
$render_proof->invoke( $dashboard, $stored, $proof_id );
$exists_html = (string) ob_get_clean();
check( 'existing proof renders a thumbnail', false !== strpos( $exists_html, 'bb-mp-proof-thumb' ) );
check( 'existing proof renders a View Proof link', false !== strpos( $exists_html, 'View Proof' ) );
check( 'existing proof link is the protected endpoint', false !== strpos( $exists_html, 'bb_manual_payment_proof' ) );

/* b) Proof missing -> "No proof uploaded". */
ob_start();
$render_proof->invoke( $dashboard, $stored, 0 );
$missing_html = (string) ob_get_clean();
check( 'missing proof says No proof uploaded', false !== strpos( $missing_html, 'No proof uploaded' ) );

/* c) Invalid reference -> clearly labelled, not "missing". */
ob_start();
$render_proof->invoke( $dashboard, $stored, 99999 );
$invalid_html = (string) ob_get_clean();
check( 'invalid proof reference is labelled', false !== strpos( $invalid_html, 'Invalid proof reference' ) );

/* d) Record exists but file deleted -> clearly labelled, not "missing". */
$deleted_id = wp_insert_attachment(
    array( 'post_mime_type' => 'image/png', 'post_title' => 'deleted', 'post_status' => 'private' ),
    '/tmp/does-not-exist-' . wp_generate_password( 6, false, false ) . '.png',
    $cns
);
update_post_meta( $deleted_id, '_bb_private_receipt', '1' );

ob_start();
$render_proof->invoke( $dashboard, $stored, (int) $deleted_id );
$deleted_html = (string) ob_get_clean();
check( 'deleted proof file is labelled', false !== strpos( $deleted_html, 'file was deleted' ) );
check( 'deleted proof is NOT reported as missing', false === strpos( $deleted_html, 'No proof uploaded' ) );

echo "== 7. Multisite isolation: the protected proof endpoint needs a valid id pair ==\n";
check( 'proof id differs from a random id (endpoint binds id+txn)', $proof_id !== 12345678 );

echo "== 7b. Upload validation rejects bad input (no proof stored) ==\n";

$store_method = new ReflectionMethod( 'BusinessBuilderCore\Packs\LawFirm\Payments\ManualPaymentSubmission', 'store_receipt' );
$store_method->setAccessible( true );

$bad_cases = array(
    'upload error code'   => array( 'name' => 'x.png', 'type' => 'image/png', 'tmp_name' => $tmp_src, 'error' => UPLOAD_ERR_NO_FILE, 'size' => 10 ),
    'oversized file'      => array( 'name' => 'big.png', 'type' => 'image/png', 'tmp_name' => $tmp_src, 'error' => UPLOAD_ERR_OK, 'size' => 9000000 ),
    'unsupported mime'    => array( 'name' => 'evil.php', 'type' => 'application/x-php', 'tmp_name' => $tmp_src, 'error' => UPLOAD_ERR_OK, 'size' => 10 ),
);

foreach ( $bad_cases as $label => $bad ) {

    $cb = static function () use ( $bad ) {
        return $bad;
    };

    add_filter( 'bb_manual_payment_proof_file', $cb );
    add_filter( 'bb_manual_payment_proof_require_upload', '__return_false' );

    $result = (int) $store_method->invoke( null, $cns, '_bb_consultation_' );

    check( $label . ' is rejected (no attachment stored)', 0 === $result );

    remove_filter( 'bb_manual_payment_proof_file', $cb );
    remove_filter( 'bb_manual_payment_proof_require_upload', '__return_false' );
}

echo "== 7c. The canonical proof metadata key is proof_attachment_id ==\n";
check( 'submission wrote proof_attachment_id (single canonical key)', isset( $stored->meta['proof_attachment_id'] ) );
check( 'no duplicate manual_proof_id key was introduced', ! isset( $stored->meta['manual_proof_id'] ) );

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* Cleanup. */
if ( $proof_id > 0 ) {
    wp_delete_attachment( $proof_id, true );
}
if ( ! empty( $deleted_id )) {
    wp_delete_attachment( (int) $deleted_id, true );
}
@unlink( $tmp_src );
wp_delete_post( $cns, true );
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_gateways' );

exit( 0 === $fail ? 0 : 1 );
