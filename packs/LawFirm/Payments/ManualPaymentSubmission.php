<?php
namespace BusinessBuilderCore\Packs\LawFirm\Payments;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentGatewayInterface;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Manual payment submission handling (shared by consultation + booking).
 *
 * When a customer chooses a MANUAL gateway (wallet / InstaPay / bank
 * transfer) and submits the form, this class records the customer-supplied
 * transaction reference, securely stores an optional uploaded receipt as a
 * PRIVATE attachment, and marks the payment "on_hold" (awaiting admin
 * verification). It NEVER marks a payment as paid.
 */
final class ManualPaymentSubmission {

    /** Max accepted receipt size (5 MB). */
    private const MAX_RECEIPT_BYTES = 5242880;

    /**
     * Allowed receipt mime types.
     *
     * @var array<string, string>
     */
    private const ALLOWED_TYPES = array(
        'image/png'       => 'png',
        'image/jpeg'      => 'jpg',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    );

    /** Non-instantiable. */
    private function __construct() {
    }

    /**
     * Process a manual payment submission for a record.
     *
     * @param string                  $object_type 'consultation' | 'appointment'.
     * @param int                     $object_id   Record id.
     * @param string                  $gateway_id  Chosen gateway id.
     * @param array                   $post        Raw POST data.
     * @param PaymentTransaction|null $transaction Pending transaction (resolved when omitted).
     * @return bool
     */
    public static function submit( string $object_type, int $object_id, string $gateway_id, array $post, ?PaymentTransaction $transaction = null ): bool {

        $object_type = sanitize_key( $object_type );
        $object_id   = absint( $object_id );
        $gateway_id  = sanitize_key( $gateway_id );

        if ( $object_id <= 0 || '' === $gateway_id ) {
            return false;
        }

        $allowed = array( 'consultation', 'appointment' );

        if ( ! in_array( $object_type, $allowed, true )) {
            return false;
        }

        $manager = new PaymentManager();
        $gateway = $manager->gateway( $gateway_id );

        if ( ! $gateway instanceof PaymentGatewayInterface ) {
            return false;
        }

        if ( ! $gateway->is_manual() ) {
            return false;
        }

        $raw_ref   = isset( $post['bb_manual_reference'] ) ? $post['bb_manual_reference'] : '';
        $reference = self::sanitize_reference( $raw_ref );

        if ( '' === $reference ) {
            return false;
        }

        $meta_key = self::prefix( $object_type );

        update_post_meta( $object_id, $meta_key . 'manual_reference', $reference );
        update_post_meta( $object_id, $meta_key . 'manual_gateway', $gateway_id );
        update_post_meta( $object_id, $meta_key . 'payment_status', 'on_hold' );
        update_post_meta( $object_id, $meta_key . 'manual_submitted_at', current_time( 'mysql' ) );

        $attachment_id = self::store_receipt( $object_id, $meta_key );

        if ( $attachment_id > 0 ) {
            update_post_meta( $object_id, $meta_key . 'manual_receipt', $attachment_id );
        }

        /*
         * Persist the SAME facts on the PAYMENT TRANSACTION so the receipt
         * and the admin review screen read them from one authoritative row.
         * The transaction's existing meta bag carries them (no duplicate
         * columns are added to the schema).
         */
        $transaction = self::ensure_transaction( $manager, $transaction, $object_type, $object_id, $gateway_id, $meta_key );

        if ( $transaction instanceof PaymentTransaction && $transaction->id > 0 ) {

            $meta = is_array( $transaction->meta ) ? $transaction->meta : array();

            $meta['manual_reference']    = $reference;
            $meta['manual_gateway']      = $gateway_id;
            $meta['manual_submitted_at'] = current_time( 'mysql' );

            /*
             * Only overwrite the stored proof when a NEW file was actually
             * received and stored. Otherwise a resubmission without a file
             * would silently erase a previously uploaded proof.
             */
            if ( $attachment_id > 0 ) {
                $meta['proof_attachment_id'] = (int) $attachment_id;
            }

            /*
             * Snapshot the customer's name/phone from the CANONICAL
             * consultation/appointment record so the receipt can always
             * show the real customer, even for manual gateways that do not
             * collect billing details at checkout (this is the same source
             * the Manual Payments dashboard reads).
             */
            $customer = self::customer_context( $object_type, $object_id, $meta_key );

            if ( '' !== $customer['name'] && empty( $meta['name'] )) {
                $meta['name'] = $customer['name'];
            }

            if ( '' !== $customer['phone'] && empty( $meta['phone'] )) {
                $meta['phone'] = $customer['phone'];
            }

            /* Mint a stable, canonical receipt number once (never a second system). */
            if ( empty( $meta['receipt_number'] ) && '' !== $transaction->public_ref ) {
                $meta['receipt_number'] = \BusinessBuilderCore\Core\Payments\Transaction\Reference::receipt_from_transaction( $transaction->public_ref );
            }

            $transaction->meta   = $meta;
            $transaction->status = 'on_hold';

            $transaction = $manager->persist( $transaction );

            /* Keep the related record pointing at the authoritative row. */
            update_post_meta( $object_id, $meta_key . 'payment_reference', $transaction->public_ref );
            update_post_meta( $object_id, $meta_key . 'transaction_id', $transaction->id );
        } else {
            /* Do not claim a manual payment was accepted without a receipt row. */
            return false;
        }

        ( new AuditLog() )->record(
            'payment.manual_submitted',
            'payment',
            $object_id,
            array(
                'gateway'   => $gateway_id,
                'reference' => $reference,
                'receipt'   => $attachment_id,
            )
        );

        /* Dashboard notification: a manual payment needs verification. */
        $entity_ref = (string) get_post_meta( $object_id, $meta_key . 'public_reference', true );

        ( new \BusinessBuilderCore\Core\Notifications\NotificationManager() )->dispatch(
            new \BusinessBuilderCore\Core\Notifications\Notification(
                'payment.manual_submitted',
                sprintf(
                    /* translators: 1: gateway, 2: object type */
                    __( 'Manual %1$s payment submitted for %2$s', 'business-builder' ),
                    $gateway->get_name(),
                    $object_type
                ),
                $reference,
                '',
                $object_id,
                'payment:manual:' . $object_id . ':' . $reference,
                array(
                    'category'    => 'payment',
                    'entity_type' => $object_type,
                    'entity_id'   => $object_id,
                    'reference'   => $entity_ref,
                    'gateway'     => $gateway_id,
                )
            )
        );

        return true;
    }

    /**
     * Resolve or create the transaction that represents this manual submission.
     *
     * Checkout normally creates this row first. This defensive path also
     * covers integrations that call submit() with an unsaved transaction (or
     * no transaction at all), preventing a success redirect with no receipt
     * to look up.
     *
     * @param PaymentManager          $manager Payments.
     * @param PaymentTransaction|null $transaction Candidate transaction.
     * @param string                  $object_type Object type.
     * @param int                     $object_id Related object id.
     * @param string                  $gateway_id Manual gateway id.
     * @param string                  $meta_key Object meta prefix.
     * @return PaymentTransaction|null
     */
    private static function ensure_transaction( PaymentManager $manager, ?PaymentTransaction $transaction, string $object_type, int $object_id, string $gateway_id, string $meta_key ): ?PaymentTransaction {

        if ( $transaction instanceof PaymentTransaction && $transaction->id > 0 ) {
            return $transaction;
        }

        if ( ! $transaction instanceof PaymentTransaction ) {
            $transaction = self::find_pending_transaction( $manager, $object_type, $object_id, $gateway_id );
        }

        if ( ! $transaction instanceof PaymentTransaction ) {
            $transaction = PaymentTransaction::from_array(
                array(
                    'public_ref'  => (string) get_post_meta( $object_id, $meta_key . 'payment_reference', true ),
                    'object_type' => $object_type,
                    'object_id'   => $object_id,
                    'gateway'     => $gateway_id,
                    'amount'      => (string) get_post_meta( $object_id, $meta_key . 'payment_amount', true ),
                    'currency'    => (string) get_post_meta( $object_id, $meta_key . 'payment_currency', true ),
                    'status'      => 'pending',
                )
            );
        }

        if ( '' === $transaction->object_type ) {
            $transaction->object_type = $object_type;
        }

        if ( $transaction->object_id <= 0 ) {
            $transaction->object_id = $object_id;
        }

        if ( '' === $transaction->gateway ) {
            $transaction->gateway = $gateway_id;
        }

        return $manager->persist( $transaction );
    }

    /**
     * The meta key prefix for an object type.
     *
     * @param string $object_type Object type.
     * @return string
     */
    public static function prefix( string $object_type ): string {

        if ( 'appointment' === sanitize_key( $object_type )) {
            return '_bb_appointment_';
        }

        return '_bb_consultation_';
    }

    /**
     * Sanitize a customer-supplied transaction reference (allow-list).
     *
     * @param mixed $raw Raw value.
     * @return string
     */
    /**
     * Read the customer's name/phone from the related record.
     *
     * Uses the object's OWN meta (the same canonical source the Manual
     * Payments dashboard reads), so the receipt and the dashboard agree.
     * Never trusts a browser-supplied name.
     *
     * @param string $object_type 'consultation' | 'appointment'.
     * @param int    $object_id   Record id.
     * @param string $meta_key    Object meta prefix.
     * @return array{name: string, phone: string}
     */
    public static function customer_context( string $object_type, int $object_id, string $meta_key ): array {

        $context = array(
            'name'  => '',
            'phone' => '',
        );

        if ( $object_id <= 0 ) {
            return $context;
        }

        if ( 'appointment' === sanitize_key( $object_type )) {
            $context['name']  = (string) get_post_meta( $object_id, $meta_key . 'client_name', true );
            $context['phone'] = (string) get_post_meta( $object_id, $meta_key . 'client_phone', true );
        } else {
            $context['name']  = (string) get_post_meta( $object_id, $meta_key . 'name', true );
            $context['phone'] = (string) get_post_meta( $object_id, $meta_key . 'phone', true );
        }

        $context['name']  = sanitize_text_field( $context['name'] );
        $context['phone'] = sanitize_text_field( $context['phone'] );

        return $context;
    }

    /**
     * Sanitize a customer-supplied transaction reference (allow-list).
     *
     * @param mixed $raw Raw value.
     * @return string
     */
    public static function sanitize_reference( $raw ): string {

        if ( ! is_scalar( $raw )) {
            return '';
        }

        $value = wp_unslash( (string) $raw );
        $value = preg_replace( '/[^A-Za-z0-9 .\-\/_]/', '', $value );
        $value = is_string( $value ) ? trim( $value ) : '';

        if ( strlen( $value ) > 120 ) {
            $value = substr( $value, 0, 120 );
        }

        return $value;
    }

    /**
     * Store an optional uploaded receipt as a private attachment.
     *
     * @param int    $object_id Record id.
     * @param string $prefix    Meta prefix.
     * @return int Attachment id, or 0.
     */
    private static function store_receipt( int $object_id, string $prefix ): int {

        /**
         * Filter the $_FILES entry used for the receipt upload.
         *
         * Production always uses $_FILES['bb_manual_receipt'] (the browser's
         * multipart upload). The filter exists so automated tests and
         * integrations can inject a file array without weakening the upload
         * checks below.
         *
         * @param array|null $file Raw upload entry.
         */
        $file = apply_filters( 'bb_manual_payment_proof_file', isset( $_FILES['bb_manual_receipt'] ) ? $_FILES['bb_manual_receipt'] : null );

        if ( ! is_array( $file )) {
            return 0;
        }

        if ( ! isset( $file['error'] ) || (int) $file['error'] !== UPLOAD_ERR_OK ) {
            return 0;
        }

        $size = isset( $file['size'] ) ? (int) $file['size'] : 0;

        if ( $size <= 0 || $size > self::MAX_RECEIPT_BYTES ) {
            return 0;
        }

        $tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

        if ( '' === $tmp_name ) {
            return 0;
        }

        /**
         * Filter whether the upload must pass PHP's is_uploaded_file() check.
         *
         * TRUE in production (a genuine HTTP upload). Automated tests may
         * set it to false to exercise the storage path with a local file.
         *
         * @param bool  $require Real upload required.
         * @param array $file    Upload entry.
         */
        $require_upload = (bool) apply_filters( 'bb_manual_payment_proof_require_upload', true, $file );

        if ( $require_upload && ! is_uploaded_file( $tmp_name )) {
            return 0;
        }

        if ( ! $require_upload && ! file_exists( $tmp_name )) {
            return 0;
        }

        $check = wp_check_filetype_and_ext( $tmp_name, isset( $file['name'] ) ? (string) $file['name'] : '' );
        $mime  = isset( $check['type'] ) ? (string) $check['type'] : '';

        if ( ! array_key_exists( $mime, self::ALLOWED_TYPES )) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = array(
            'test_form'   => false,
            'test_type'   => true,
            'test_upload' => $require_upload,
            'mimes'       => array(
                'png'      => 'image/png',
                'jpg|jpeg' => 'image/jpeg',
                'webp'     => 'image/webp',
                'pdf'      => 'application/pdf',
            ),
        );

        /*
         * Production always uses wp_handle_upload (a genuine HTTP upload).
         * When the strict upload check is disabled (tests/integrations),
         * use wp_handle_sideload, the WordPress-blessed path for a local
         * file - it is never used while the strict check is on.
         */
        $moved = $require_upload
            ? wp_handle_upload( $file, $overrides )
            : wp_handle_sideload( $file, $overrides );

        if ( ! is_array( $moved ) || isset( $moved['error'] ) || empty( $moved['file'] )) {
            return 0;
        }

        $attachment = array(
            'post_mime_type' => isset( $moved['type'] ) ? (string) $moved['type'] : $mime,
            'post_title'     => $prefix . 'receipt-' . $object_id,
            'post_content'   => '',
            'post_status'    => 'private',
        );

        $attachment_id = wp_insert_attachment( $attachment, (string) $moved['file'], $object_id );

        if ( is_wp_error( $attachment_id ) || $attachment_id <= 0 ) {
            return 0;
        }

        update_post_meta( (int) $attachment_id, '_bb_private_receipt', '1' );

        return (int) $attachment_id;
    }

    /**
     * Find the most recent pending/processing transaction for a record +
     * gateway (used when the caller did not pass the transaction in).
     *
     * Only ever looks at THIS site's transactions, so one site can never
     * resolve another site's payment (Multisite isolation).
     *
     * @param PaymentManager $manager    Payments.
     * @param string         $object_type Object type.
     * @param int            $object_id   Record id.
     * @param string         $gateway_id  Gateway id.
     * @return PaymentTransaction|null
     */
    private static function find_pending_transaction( PaymentManager $manager, string $object_type, int $object_id, string $gateway_id ): ?PaymentTransaction {

        $rows = $manager->transactions_for_object( $object_type, $object_id );

        foreach ( $rows as $row ) {

            if ( ! is_array( $row )) {
                continue;
            }

            $row_gateway = isset( $row['gateway'] ) ? sanitize_key( (string) $row['gateway'] ) : '';
            $row_status  = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';

            if ( $row_gateway !== $gateway_id ) {
                continue;
            }

            if ( ! in_array( $row_status, array( 'pending', 'processing', 'on_hold', 'awaiting_payment' ), true )) {
                continue;
            }

            return PaymentTransaction::from_array( $row );
        }

        return null;
    }
}
