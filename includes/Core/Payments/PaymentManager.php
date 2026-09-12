<?php

namespace BusinessBuilderCore\Core\Payments;

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
     * Option: active gateway id for this site.
     */
    private const OPTION_ACTIVE = 'bb_payment_active_gateway';

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
}
