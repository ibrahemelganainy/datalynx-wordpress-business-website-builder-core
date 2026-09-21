<?php

namespace BusinessBuilderCore\Core\Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Minimal, per-site audit log (spec 28).
 *
 * Records important actions (status changes, manual payment
 * verification, appointment changes, gateway configuration changes) as
 * a bounded, site-scoped option list. Never records secrets.
 *
 * Multisite: stored via options => automatically isolated per site.
 */
class AuditLog {

    /**
     * Option name.
     */
    private const OPTION_NAME = 'bb_audit_log';

    /**
     * Max entries kept per site.
     */
    private const LIMIT = 500;

    /**
     * Record an audit entry.
     *
     * @param string $action    Action slug, e.g. 'consultation.status_changed'.
     * @param string $object    Object type, e.g. 'consultation'.
     * @param int    $object_id Object id.
     * @param array  $context   Extra, non-sensitive context.
     */
    public function record(
        string $action,
        string $object = '',
        int $object_id = 0,
        array $context = array()
    ): void {

        /* Strip anything that looks like a secret before storing. */
        $context = $this->scrub( $context );

        $log = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $log ) ) {
            $log = array();
        }

        $user = wp_get_current_user();

        $log[] = array(
            'action'    => $action,
            'object'    => $object,
            'object_id' => $object_id,
            'context'   => $context,
            'user_id'   => $user instanceof \WP_User ? (int) $user->ID : 0,
            'user'      => $user instanceof \WP_User ? (string) $user->user_login : '',
            'user_name' => $user instanceof \WP_User ? (string) $user->display_name : '',
            /*
             * A public reference carried through from the caller (e.g. the
             * CNS-/APT-/TXN- reference) so the activity timeline can show
             * WHAT was acted on, not just the action name.
             */
            'reference' => isset( $context['reference'] ) ? sanitize_text_field( (string) $context['reference'] ) : '',
            'time'      => time(),
        );

        if ( count( $log ) > self::LIMIT ) {
            $log = array_slice( $log, -self::LIMIT );
        }

        update_option( self::OPTION_NAME, $log, false );
    }

    /**
     * Get recent entries (newest first).
     *
     * @param int $limit Max entries.
     * @return array<int, array<string, mixed>>
     */
    public function recent( int $limit = 50 ): array {

        $log = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $log ) ) {
            return array();
        }

        return array_slice( array_reverse( $log ), 0, max( 1, $limit ) );
    }

    /**
     * Filter audit entries for the activity timeline.
     *
     * Reuses the same storage; adds category + reference filtering so the
     * Notifications & Activity page can show a real timeline alongside the
     * notifications without a second log.
     *
     * @param string $category 'all' | consultation | appointment | payment | system.
     * @param string $search   Free-text (action/reference/user).
     * @param int    $days     Day window (0 = all).
     * @param int    $limit    Max entries.
     * @param int    $offset   Entries to skip (pagination).
     * @return array<int, array<string, mixed>>
     */
    public function query( string $category = 'all', string $search = '', int $days = 0, int $limit = 50, int $offset = 0 ): array {

        $log = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $log ) ) {
            return array();
        }

        $items  = array_reverse( $log );
        $needle = strtolower( trim( $search ) );
        $cutoff = $days > 0 ? time() - ( $days * DAY_IN_SECONDS ) : 0;
        $offset = max( 0, $offset );

        $out     = array();
        $skipped = 0;

        foreach ( $items as $entry ) {

            if ( ! is_array( $entry )) {
                continue;
            }

            if ( ! self::matches_category( $entry, $category )) {
                continue;
            }

            if ( $cutoff > 0 && (int) ( $entry['time'] ?? 0 ) < $cutoff ) {
                continue;
            }

            if ( '' !== $needle ) {
                $haystack = strtolower(
                    (string) ( $entry['action'] ?? '' ) . ' '
                    . (string) ( $entry['object'] ?? '' ) . ' '
                    . (string) ( $entry['reference'] ?? '' ) . ' '
                    . (string) ( $entry['user_name'] ?? '' )
                );

                if ( false === strpos( $haystack, $needle )) {
                    continue;
                }
            }

            if ( $skipped < $offset ) {
                $skipped++;
                continue;
            }

            $out[] = $entry;

            if ( count( $out ) >= max( 1, $limit )) {
                break;
            }
        }

        return $out;
    }

    /**
     * Whether an audit entry belongs to a filter category.
     *
     * @param array  $entry    Entry.
     * @param string $category Category filter.
     * @return bool
     */
    public static function matches_category( array $entry, string $category ): bool {

        $category = sanitize_key( $category );

        if ( '' === $category || 'all' === $category ) {
            return true;
        }

        $action = isset( $entry['action'] ) ? sanitize_key( (string) $entry['action'] ) : '';
        $object = isset( $entry['object'] ) ? sanitize_key( (string) $entry['object'] ) : '';

        if ( 'payment' === $category ) {
            return 0 === strpos( $action, 'payment' ) || 'payment' === $object;
        }

        if ( 'consultation' === $category ) {
            return 0 === strpos( $action, 'consultation' ) || 'consultation' === $object;
        }

        if ( 'appointment' === $category ) {
            return 0 === strpos( $action, 'appointment' ) || 'appointment' === $object;
        }

        /* 'system' is everything that is not one of the business categories. */
        if ( 'system' === $category ) {
            return ! self::matches_category( $entry, 'payment' )
                && ! self::matches_category( $entry, 'consultation' )
                && ! self::matches_category( $entry, 'appointment' );
        }

        return false;
    }

    /**
     * Remove sensitive keys from context recursively.
     *
     * @param array $context Context.
     * @return array
     */
    private function scrub( array $context ): array {

        $blocked = array(
            'secret',
            'secret_key',
            'api_key',
            'webhook_secret',
            'password',
            'token',
            'hmac',
            'private_key',
        );

        foreach ( $context as $key => $value ) {

            if ( is_array( $value ) ) {
                $context[ $key ] = $this->scrub( $value );
                continue;
            }

            if ( in_array( strtolower( (string) $key ), $blocked, true ) ) {
                $context[ $key ] = '***';
            }
        }

        return $context;
    }
}
