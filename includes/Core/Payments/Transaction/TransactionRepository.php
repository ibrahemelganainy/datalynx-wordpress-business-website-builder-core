<?php

namespace BusinessBuilderCore\Core\Payments\Transaction;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Default transaction store, backed by the private bb_payment CPT.
 *
 * Persistence model:
 *   - one post per transaction (posts table => per-site Multisite),
 *   - every field stored as post meta (prefix _bb_payment_),
 *   - never stores card data, CVV or gateway credentials.
 *
 * A shared meta-map (field_map) is the single source of truth for the
 * field <-> meta-key relationship, so PaymentManager can mirror the
 * exact same shape into its legacy option store for backward
 * compatibility without duplicating key knowledge.
 */
class TransactionRepository implements TransactionStore {

    /**
     * Providers that must never be persisted.
     */
    private const SENSITIVE_TAGS = array();

    /**
     * Whether the post type registry has been ensured this request.
     */
    private static bool $ensured = false;

    /**
     * Constructor.
     */
    public function __construct() {

        $this->ensure_post_type();
    }

    /**
     * Make sure the CPT is registered before any query runs.
     *
     * register_post_type() is idempotent, so calling it defensively is
     * safe even when the pack/init hook has not fired yet (e.g. during
     * a REST webhook or a CLI runtime test).
     */
    private function ensure_post_type(): void {

        if ( self::$ensured ) {
            return;
        }

        $exists = post_type_exists( PaymentTransactionPostType::POST_TYPE );

        if ( false === $exists ) {
            ( new PaymentTransactionPostType() )->register_post_type();
        }

        self::$ensured = true;
    }

    /**
     * The canonical field => meta-key map.
     *
     * @return array<string, string>
     */
    public static function field_map(): array {

        $map = array(
            'public_ref'      => 'public_ref',
            'object_type'     => 'object_type',
            'object_id'       => 'object_id',
            'consultation_id' => 'consultation_id',
            'gateway'         => 'gateway',
            'reference'       => 'reference',
            'amount'          => 'amount',
            'currency'        => 'currency',
            'status'          => 'status',
            'meta'            => 'meta',
            'created_at'      => 'created_at',
            'updated_at'      => 'updated_at',
        );

        foreach ( $map as $field => $key ) {
            $map[ $field ] = PaymentTransactionPostType::meta_key( $key );
        }

        return $map;
    }

    /**
     * Persist a transaction.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return PaymentTransaction
     */
    public function save( PaymentTransaction $transaction ): PaymentTransaction {

        $this->ensure_post_type();

        $title = '' !== $transaction->public_ref
            ? $transaction->public_ref
            : Reference::transaction();

        if ( '' === $transaction->public_ref ) {
            $transaction->public_ref = $title;
        }

        if ( '' === $transaction->created_at ) {
            $transaction->created_at = current_time( 'mysql' );
        }

        $transaction->updated_at = current_time( 'mysql' );

        $postarr = array(
            'post_type'   => PaymentTransactionPostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
        );

        if ( $transaction->id > 0 ) {
            $postarr['ID'] = $transaction->id;
            $post_id       = wp_update_post( $postarr, true );
        } else {
            $post_id = wp_insert_post( $postarr, true );
        }

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return $transaction;
        }

        $transaction->id = (int) $post_id;

        $this->write_meta( $transaction );

        return $transaction;
    }

    /**
     * Write all meta for a transaction.
     *
     * @param PaymentTransaction $transaction Transaction.
     */
    private function write_meta( PaymentTransaction $transaction ): void {

        $map = self::field_map();

        $values = array(
            'public_ref'      => $transaction->public_ref,
            'object_type'     => $transaction->object_type,
            'object_id'       => $transaction->object_id,
            'consultation_id' => $transaction->consultation_id,
            'gateway'         => $transaction->gateway,
            'reference'       => $transaction->reference,
            'amount'          => $transaction->amount,
            'currency'        => $transaction->currency,
            'status'          => $transaction->status,
            'meta'            => $transaction->meta,
            'created_at'      => $transaction->created_at,
            'updated_at'      => $transaction->updated_at,
        );

        foreach ( $values as $field => $value ) {
            update_post_meta( $transaction->id, $map[ $field ], $value );
        }
    }

    /**
     * Find by internal id.
     *
     * @param int $id Internal id.
     * @return PaymentTransaction|null
     */
    public function find( int $id ): ?PaymentTransaction {

        $id = absint( $id );

        if ( $id <= 0 ) {
            return null;
        }

        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || PaymentTransactionPostType::POST_TYPE !== $post->post_type ) {
            return null;
        }

        return $this->hydrate( $post );
    }

    /**
     * Find by provider reference.
     *
     * @param string $reference Provider reference.
     * @return PaymentTransaction|null
     */
    public function find_by_reference( string $reference ): ?PaymentTransaction {

        $reference = sanitize_text_field( $reference );

        if ( '' === $reference ) {
            return null;
        }

        return $this->find_by_meta( self::field_map()['reference'], $reference );
    }

    /**
     * Find by public reference.
     *
     * @param string $public_ref Public reference.
     * @return PaymentTransaction|null
     */
    public function find_by_public_ref( string $public_ref ): ?PaymentTransaction {

        $public_ref = sanitize_text_field( $public_ref );

        if ( '' === $public_ref ) {
            return null;
        }

        return $this->find_by_meta( self::field_map()['public_ref'], $public_ref );
    }

    /**
     * Find a single transaction by a meta value.
     *
     * @param string $meta_key Meta key.
     * @param string $value    Value.
     * @return PaymentTransaction|null
     */
    private function find_by_meta( string $meta_key, string $value ): ?PaymentTransaction {

        $this->ensure_post_type();

        $query = new \WP_Query(
            array(
                'post_type'              => PaymentTransactionPostType::POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'   => $meta_key,
                        'value' => $value,
                    ),
                ),
            )
        );

        $has_posts = ! empty( $query->posts );

        if ( false === $has_posts ) {
            return null;
        }

        $post = get_post( (int) $query->posts[0] );

        return $post instanceof \WP_Post ? $this->hydrate( $post ) : null;
    }

    /**
     * All transactions for a related object, newest first.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object id.
     * @return PaymentTransaction[]
     */
    public function for_object( string $object_type, int $object_id ): array {

        $object_type = sanitize_key( $object_type );
        $object_id   = absint( $object_id );

        if ( '' === $object_type || $object_id <= 0 ) {
            return array();
        }

        $this->ensure_post_type();

        $query = new \WP_Query(
            array(
                'post_type'              => PaymentTransactionPostType::POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => 100,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    'relation' => 'AND',
                    array(
                        'key'   => self::field_map()['object_type'],
                        'value' => $object_type,
                    ),
                    array(
                        'key'     => self::field_map()['object_id'],
                        'value'   => $object_id,
                        'type'    => 'NUMERIC',
                    ),
                ),
            )
        );

        return $this->hydrate_many( $query->posts );
    }

    /**
     * Recent transactions, newest first.
     *
     * @param int $limit Maximum rows.
     * @return PaymentTransaction[]
     */
    public function all( int $limit = 50 ): array {

        $limit = max( 1, min( 500, $limit ) );

        $this->ensure_post_type();

        $query = new \WP_Query(
            array(
                'post_type'              => PaymentTransactionPostType::POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => $limit,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
            )
        );

        return $this->hydrate_many( $query->posts );
    }

    /**
     * Update a transaction's status.
     *
     * @param int    $id        Internal id.
     * @param string $status    New status.
     * @param string $reference Provider reference (optional).
     * @return PaymentTransaction|null
     */
    public function update_status( int $id, string $status, string $reference = '' ): ?PaymentTransaction {

        $transaction = $this->find( $id );

        if ( null === $transaction ) {
            return null;
        }

        $transaction->status     = sanitize_key( $status );
        $transaction->updated_at = current_time( 'mysql' );

        if ( '' !== $reference ) {
            $transaction->reference = sanitize_text_field( $reference );
        }

        return $this->save( $transaction );
    }

    /**
     * Hydrate a single post into a value object.
     *
     * @param \WP_Post $post Post.
     * @return PaymentTransaction
     */
    private function hydrate( \WP_Post $post ): PaymentTransaction {

        return self::from_post( $post );
    }

    /**
     * Hydrate many posts.
     *
     * @param array<int, \WP_Post|int> $posts Posts or ids.
     * @return PaymentTransaction[]
     */
    private function hydrate_many( array $posts ): array {

        $out = array();

        foreach ( $posts as $post ) {

            if ( ! $post instanceof \WP_Post ) {
                $post = get_post( (int) $post );
            }

            if ( $post instanceof \WP_Post ) {
                $out[] = self::from_post( $post );
            }
        }

        return $out;
    }

    /**
     * Build a value object from a payment post.
     *
     * Public so PaymentManager can reuse the exact same shape.
     *
     * @param \WP_Post $post Post.
     * @return PaymentTransaction
     */
    public static function from_post( \WP_Post $post ): PaymentTransaction {

        $map = self::field_map();

        $meta = get_post_meta( $post->ID, $map['meta'], true );

        $data = array(
            'id'              => (int) $post->ID,
            'public_ref'      => (string) get_post_meta( $post->ID, $map['public_ref'], true ),
            'object_type'     => (string) get_post_meta( $post->ID, $map['object_type'], true ),
            'object_id'       => (int) get_post_meta( $post->ID, $map['object_id'], true ),
            'consultation_id' => (int) get_post_meta( $post->ID, $map['consultation_id'], true ),
            'gateway'         => (string) get_post_meta( $post->ID, $map['gateway'], true ),
            'reference'       => (string) get_post_meta( $post->ID, $map['reference'], true ),
            'amount'          => (string) get_post_meta( $post->ID, $map['amount'], true ),
            'currency'        => (string) get_post_meta( $post->ID, $map['currency'], true ),
            'status'          => (string) get_post_meta( $post->ID, $map['status'], true ),
            'meta'            => is_array( $meta ) ? $meta : array(),
            'created_at'      => (string) get_post_meta( $post->ID, $map['created_at'], true ),
            'updated_at'      => (string) get_post_meta( $post->ID, $map['updated_at'], true ),
        );

        return PaymentTransaction::from_array( $data );
    }
}
