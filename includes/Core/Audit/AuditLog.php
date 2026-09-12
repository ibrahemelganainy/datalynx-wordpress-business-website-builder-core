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
