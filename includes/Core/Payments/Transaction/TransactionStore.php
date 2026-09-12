<?php

namespace BusinessBuilderCore\Core\Payments\Transaction;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Transaction persistence contract.
 *
 * Phase F introduces an explicit storage abstraction so the payment
 * subsystem no longer depends on a single option-array shape. The
 * default implementation (TransactionRepository) writes a private
 * post type, but a pack or extension can register its own store
 * without touching PaymentManager or any caller.
 *
 * All methods operate on the CURRENT site only, keeping Multisite
 * data isolated per site (no get_site_option / switch_to_blog).
 */
interface TransactionStore {

    /**
     * Persist a transaction, returning the stored value object (with a
     * populated id when it is new).
     *
     * @param PaymentTransaction $transaction Transaction to store.
     * @return PaymentTransaction
     */
    public function save( PaymentTransaction $transaction ): PaymentTransaction;

    /**
     * Find a transaction by its internal id.
     *
     * @param int $id Internal id.
     * @return PaymentTransaction|null
     */
    public function find( int $id ): ?PaymentTransaction;

    /**
     * Find a transaction by its provider reference.
     *
     * @param string $reference Provider reference.
     * @return PaymentTransaction|null
     */
    public function find_by_reference( string $reference ): ?PaymentTransaction;

    /**
     * Find a transaction by its public, non-sequential reference.
     *
     * @param string $public_ref Public reference.
     * @return PaymentTransaction|null
     */
    public function find_by_public_ref( string $public_ref ): ?PaymentTransaction;

    /**
     * All transactions for a related object, newest first.
     *
     * @param string $object_type Object type (consultation|appointment).
     * @param int    $object_id   Object id.
     * @return PaymentTransaction[]
     */
    public function for_object( string $object_type, int $object_id ): array;

    /**
     * Recent transactions, newest first.
     *
     * @param int $limit Maximum number of rows.
     * @return PaymentTransaction[]
     */
    public function all( int $limit = 50 ): array;

    /**
     * Update a transaction's status (and optionally its provider
     * reference), returning the updated object.
     *
     * @param int    $id        Internal id.
     * @param string $status    New status.
     * @param string $reference Provider reference (optional).
     * @return PaymentTransaction|null
     */
    public function update_status( int $id, string $status, string $reference = '' ): ?PaymentTransaction;
}
