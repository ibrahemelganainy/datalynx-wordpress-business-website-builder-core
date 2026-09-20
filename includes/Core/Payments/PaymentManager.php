<?php

namespace BusinessBuilderCore\Core\Payments;

use BusinessBuilderCore\Core\Payments\Transaction\TransactionRepository;
use BusinessBuilderCore\Core\Payments\Transaction\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central payment manager.
 *
 * Owns the gateway registry, active-gateway resolution, secure settings
 * storage (with secret masking), and the transaction store. All payment
 * operations go through here so no provider logic leaks into business
 * code (spec 5/6/7/9/10).
 */
class PaymentManager {

    /**
     * Option: active gateway id for this site (legacy single-gateway).
     */
    private const OPTION_ACTIVE = 'bb_payment_active_gateway';

    /**
     * Option: enabled gateway ids for this site (multi-gateway).
     *
     * The administrator may enable several gateways at once; the
     * frontend then offers every enabled + configured option (spec: Part 3).
     */
    private const OPTION_ENABLED = 'bb_payment_enabled_gateways';

    /**
     * Option: transaction list for this site.
     */
    private const OPTION_TXNS = 'bb_payment_transactions';

    /**
     * Max transactions kept.
     */
    private const TXN_LIMIT = 500;

    /**
     * Registered gateways.
     *
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = array();

    /**
     * Transaction persistence layer (Phase F).
     *
     * Lazily instantiated so the option-store behaviour of earlier
     * phases is unaffected until a caller needs the CPT-backed store.
     */
    protected ?TransactionStore $store = null;

    /**
     * Constructor.
     */
    public function __construct() {

        /*
         * Built-in gateways. Configuration-ready providers register
         * their real credential schema here; manual providers are
         * fully functional (spec 6 / 35).
         */
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\PaymobGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\StripeGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\PayPalGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\FawryGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\BankTransferGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\WalletGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\InstaPayGateway() );
        $this->register_gateway( new \BusinessBuilderCore\Core\Payments\Gateways\XPayGateway() );

        /**
         * Allow gateways/extensions to register additional gateways.
         *
         * @param PaymentManager $manager This manager.
         */
        do_action( 'bb_register_payment_gateways', $this );
    }

    /**
     * Register a gateway.
     *
     * @param PaymentGatewayInterface $gateway Gateway.
     */
    public function register_gateway( PaymentGatewayInterface $gateway ): void {

        $this->gateways[ $gateway->get_id() ] = $gateway;
    }

    /**
     * All registered gateways.
     *
     * @return array<string, PaymentGatewayInterface>
     */
    public function gateways(): array {

        return $this->gateways;
    }

    /**
     * Get a gateway by id.
     *
     * @param string $id Gateway id.
     * @return PaymentGatewayInterface|null
     */
    public function gateway( string $id ): ?PaymentGatewayInterface {

        $id = sanitize_key( $id );

        return $this->gateways[ $id ] ?? null;
    }

    /**
     * Active gateway id for this site.
     *
     * @return string
     */
    public function active_gateway_id(): string {

        return sanitize_key( (string) get_option( self::OPTION_ACTIVE, '' ) );
    }

    /**
     * The active gateway instance, if valid.
     *
     * @return PaymentGatewayInterface|null
     */
    public function active_gateway(): ?PaymentGatewayInterface {

        $id = $this->active_gateway_id();

        if ( '' === $id ) {
            return null;
        }

        return $this->gateway( $id );
    }

    /**
     * Set the active gateway.
     *
     * @param string $id Gateway id.
     * @return bool
     */
    public function set_active_gateway( string $id ): bool {

        $id = sanitize_key( $id );

        if ( '' !== $id && null === $this->gateway( $id ) ) {
            return false;
        }

        update_option( self::OPTION_ACTIVE, $id, false );

        return true;
    }

    /**
     * Enabled gateway ids for this site.
     *
     * Backward compatible: if the new multi-gateway option is empty but a
     * legacy single active gateway exists, that one is treated as the
     * only enabled gateway.
     *
     * @return string[]
     */
    public function enabled_gateways(): array {

        $raw = get_option( self::OPTION_ENABLED, null );

        if ( is_array( $raw ) ) {

            $ids = array();

            foreach ( $raw as $id ) {

                $id = sanitize_key( (string) $id );

                if ( '' !== $id && null !== $this->gateway( $id ) ) {
                    $ids[] = $id;
                }
            }

            return array_values( array_unique( $ids ));
        }

        /* Legacy fallback: the single active gateway, if any. */
        $active = $this->active_gateway_id();

        return '' !== $active ? array( $active ) : array();
    }

    /**
     * Whether a gateway is enabled for this site.
     *
     * @param string $id Gateway id.
     * @return bool
     */
    public function is_gateway_enabled( string $id ): bool {

        $id = sanitize_key( $id );

        return in_array( $id, $this->enabled_gateways(), true );
    }

    /**
     * Set the full list of enabled gateways for this site.
     *
     * @param string[] $ids Gateway ids.
     * @return bool
     */
    public function set_enabled_gateways( array $ids ): bool {

        $clean = array();

        foreach ( $ids as $id ) {

            $id = sanitize_key( (string) $id );

            if ( '' !== $id && null !== $this->gateway( $id ) ) {
                $clean[] = $id;
            }
        }

        $clean = array_values( array_unique( $clean ));

        /*
         * Keep the legacy active gateway in sync with the first enabled
         * one so any remaining single-gateway code path keeps working.
         */
        update_option( self::OPTION_ACTIVE, $clean[0] ?? '', false );

        return update_option( self::OPTION_ENABLED, $clean, false );
    }

    /**
     * Gateways that are enabled AND fully configured.
     *
     * This is exactly what the frontend should offer a customer: nothing
     * incomplete or disabled ever appears (spec: Part 3).
     *
     * @return array<string, PaymentGatewayInterface>
     */
    public function available_gateways(): array {

        $out = array();

        foreach ( $this->gateways() as $id => $gateway ) {

            if ( ! $this->is_gateway_enabled( $id ) ) {
                continue;
            }

            if ( ! $gateway->is_configured() ) {
                continue;
            }

            $out[ $id ] = $gateway;
        }

        return $out;
    }

    /**
     * Resolve a caller-supplied list of gateway ids to available ones.
     *
     * Used to honour a section's own gateway selection while still
     * rejecting disabled/unconfigured gateways (spec: Part 4).
     *
     * @param string[] $ids Requested gateway ids.
     * @return array<string, PaymentGatewayInterface>
     */
    public function resolve_gateways( array $ids ): array {

        $available = $this->available_gateways();

        if ( empty( $ids ) ) {
            return $available;
        }

        $out = array();

        foreach ( $ids as $id ) {

            $id = sanitize_key( (string) $id );

            if ( isset( $available[ $id ] ) ) {
                $out[ $id ] = $available[ $id ];
            }
        }

        /* Never return an empty set when the caller gave only bad ids. */
        return empty( $out ) ? $available : $out;
    }

    /**
     * Persist settings for a gateway.
     *
     * Secret fields: when the submitted value is empty AND a value is
     * already stored, the existing secret is preserved (spec 9).
     *
     * @param string $gateway_id Gateway id.
     * @param array  $input      Raw settings input.
     * @return bool
     */
    public function save_settings( string $gateway_id, array $input ): bool {

        $gateway = $this->gateway( $gateway_id );

        if ( null === $gateway ) {
            return false;
        }

        $schema = $gateway->get_settings_schema();

        $all = get_option( AbstractGateway::OPTION_NAME, array() );

        if ( ! is_array( $all ) ) {
            $all = array();
        }

        $current = isset( $all[ $gateway_id ] ) && is_array( $all[ $gateway_id ] )
            ? $all[ $gateway_id ]
            : array();

        $clean = array();

        foreach ( $schema as $key => $field ) {

            $key = sanitize_key( $key );

            if ( '' === $key ) {
                continue;
            }

            $type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

            $is_secret = ! empty( $field['secret'] );

            $value = isset( $input[ $key ] ) ? $input[ $key ] : '';

            /*
             * Preserve an existing secret when the field is left blank.
             */
            if ( $is_secret && '' === trim( (string) $value ) ) {
                if ( isset( $current[ $key ] ) ) {
                    $clean[ $key ] = $current[ $key ];
                }
                continue;
            }

            $clean[ $key ] = $this->sanitize_field( $type, $value );
        }

        $all[ $gateway_id ] = $clean;

        update_option( AbstractGateway::OPTION_NAME, $all, false );

        return true;
    }

    /**
     * Sanitize a settings field by declared type.
     *
     * @param string $type  Field type.
     * @param mixed  $value Raw value.
     * @return string
     */
    private function sanitize_field( string $type, $value ): string {

        $value = is_scalar( $value ) ? (string) $value : '';

        if ( 'password' === $type || 'textarea' === $type ) {
            /* Secrets: keep printable characters only, no HTML. */
            return trim( wp_strip_all_tags( $value ) );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Mask a secret for display: mask all but the last 4 chars.
     *
     * @param string $secret Secret value.
     * @return string
     */
    public static function mask_secret( string $secret ): string {

        $len = strlen( $secret );

        if ( 0 === $len ) {
            return '';
        }

        if ( $len <= 4 ) {
            return str_repeat( '*', $len );
        }

        return str_repeat( '*', $len - 4 ) . substr( $secret, -4 );
    }

    /* ------------------------------------------------------------------
     * Transactions
     * ------------------------------------------------------------------ */

    /**
     * Create (store) a transaction.
     *
     * @param array $data Transaction data.
     * @return PaymentTransaction
     */
    public function create_transaction( array $data ): PaymentTransaction {

        $txns = get_option( self::OPTION_TXNS, array() );

        if ( ! is_array( $txns ) ) {
            $txns = array();
        }

        $data['id']         = count( $txns ) + 1;
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );

        if ( empty( $data['public_ref'] ) ) {
            $data['public_ref'] = self::generate_public_reference();
        }

        $txn = PaymentTransaction::from_array( $data );

        $txns[ $txn->id ] = $txn->to_array();

        if ( count( $txns ) > self::TXN_LIMIT ) {
            $txns = array_slice( $txns, -self::TXN_LIMIT, null, true );
        }

        update_option( self::OPTION_TXNS, $txns, false );

        return $txn;
    }

    /**
     * Update a transaction's status by id.
     *
     * @param int    $id        Transaction id.
     * @param string $status    New status.
     * @param string $reference Provider reference.
     * @return bool
     */
    public function update_transaction( int $id, string $status, string $reference = '' ): bool {

        $txns = get_option( self::OPTION_TXNS, array() );

        if ( ! is_array( $txns ) || ! isset( $txns[ $id ] ) ) {
            return false;
        }

        $txns[ $id ]['status']     = sanitize_key( $status );
        $txns[ $id ]['updated_at'] = current_time( 'mysql' );

        if ( '' !== $reference ) {
            $txns[ $id ]['reference'] = sanitize_text_field( $reference );
        }

        update_option( self::OPTION_TXNS, $txns, false );

        return true;
    }

    /**
     * Find a transaction by provider reference.
     *
     * @param string $reference Provider reference.
     * @return PaymentTransaction|null
     */
    public function find_by_reference( string $reference ): ?PaymentTransaction {

        $txns = get_option( self::OPTION_TXNS, array() );

        if ( ! is_array( $txns ) ) {
            return null;
        }

        foreach ( $txns as $row ) {

            if ( isset( $row['reference'] ) && $row['reference'] === $reference ) {
                return PaymentTransaction::from_array( $row );
            }
        }

        return null;
    }

    /**
     * Generate a secure, non-sequential public transaction reference.
     *
     * @return string
     */
    public static function generate_public_reference(): string {

        return 'TXN-' . strtoupper( self::random_token( 10 ));
    }

    /**
     * Cryptographic random token (CSPRNG) with an unambiguous alphabet.
     *
     * @param int $length Length.
     * @return string
     */
    private static function random_token( int $length ): string {

        $length = max( 4, $length );

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $out = '';

        try {

            $bytes = random_bytes( $length );

            for ( $i = 0; $i < $length; $i++ ) {
                $index = ord( $bytes[ $i ] ) % strlen( $alphabet );
                $out  .= $alphabet[ $index ];
            }

            return $out;

        } catch ( \Exception $exception ) {

            $digest = md5( wp_generate_password( 32, false, false ) . microtime( true ) . wp_rand() );

            return strtoupper( substr( $digest, 0, $length ));
        }
    }

    /**
     * Find a transaction by its public reference.
     *
     * @param string $public_ref Public reference.
     * @return PaymentTransaction|null
     */
    public function find_by_public_ref( string $public_ref ): ?PaymentTransaction {

        $public_ref = sanitize_text_field( $public_ref );

        if ( '' === $public_ref ) {
            return null;
        }

        $txns = get_option( self::OPTION_TXNS, array() );

        if ( ! is_array( $txns ) ) {
            return null;
        }

        foreach ( $txns as $row ) {

            if ( isset( $row['public_ref'] ) && $row['public_ref'] === $public_ref ) {
                return PaymentTransaction::from_array( $row );
            }
        }

        return null;
    }

    /**
     * All transactions for a related object, newest first.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object id.
     * @return array<int, array<string, mixed>>
     */
    public function transactions_for_object( string $object_type, int $object_id ): array {

        $object_type = sanitize_key( $object_type );

        $out = array();

        foreach ( $this->transactions( self::TXN_LIMIT ) as $txn ) {

            if ( ! is_array( $txn ) ) {
                continue;
            }

            $type = isset( $txn['object_type'] ) ? (string) $txn['object_type'] : 'consultation';
            $id   = isset( $txn['object_id'] ) ? (int) $txn['object_id'] : (int) ( $txn['consultation_id'] ?? 0 );

            if ( $type === $object_type && $id === $object_id ) {
                $out[] = $txn;
            }
        }

        return $out;
    }

    /**
     * All transactions (newest first).
     *
     * @param int $limit Max items.
     * @return array<int, array<string, mixed>>
     */
    public function transactions( int $limit = 50 ): array {

        $txns = get_option( self::OPTION_TXNS, array() );

        if ( ! is_array( $txns ) ) {
            return array();
        }

        return array_slice( array_reverse( $txns, true ), 0, max( 1, $limit ) );
    }
    /* ------------------------------------------------------------------
     * Phase F: CPT-backed transaction store (additive)
     * ------------------------------------------------------------------ */

    /**
     * The transaction store (lazy, filterable).
     *
     * @return TransactionStore
     */
    public function store(): TransactionStore {

        if ( $this->store instanceof TransactionStore ) {
            return $this->store;
        }

        $default = new TransactionRepository();

        $store = apply_filters( 'bb_payment_transaction_store', $default, $this );

        $this->store = $store instanceof TransactionStore ? $store : $default;

        return $this->store;
    }

    /**
     * Persist a transaction to the CPT store AND mirror it into the
     * legacy option store so both readers stay consistent.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return PaymentTransaction The stored transaction (with id).
     */
    public function persist( PaymentTransaction $transaction ): PaymentTransaction {

        $stored = $this->store()->save( $transaction );

        $this->mirror_to_option_store( $stored );

        return $stored;
    }

    /**
     * Find a transaction by its store id.
     *
     * @param int $id Transaction id.
     * @return PaymentTransaction|null
     */
    public function find_transaction( int $id ): ?PaymentTransaction {

        return $this->store()->find( $id );
    }

    /**
     * Record a status change through the store, mirroring the legacy
     * option store (idempotent).
     *
     * @param int    $id        Transaction id.
     * @param string $status    New status.
     * @param string $reference Provider reference (optional).
     * @return PaymentTransaction|null
     */
    public function record_status_change( int $id, string $status, string $reference = '' ): ?PaymentTransaction {

        $updated = $this->store()->update_status( $id, $status, $reference );

        if ( $updated instanceof PaymentTransaction ) {
            $this->mirror_to_option_store( $updated );
        }

        return $updated;
    }

    /**
     * Mirror a stored transaction into the legacy option array.
     *
     * @param PaymentTransaction $transaction Transaction.
     */
    private function mirror_to_option_store( PaymentTransaction $transaction ): void {

        if ( $transaction->id <= 0 ) {
            return;
        }

        $txns = (array) get_option( self::OPTION_TXNS, array() );

        $txns[ $transaction->id ] = $transaction->to_array();

        if ( count( $txns ) > self::TXN_LIMIT ) {
            $txns = array_slice( $txns, -self::TXN_LIMIT, null, true );
        }

        update_option( self::OPTION_TXNS, $txns, false );
    }
}
