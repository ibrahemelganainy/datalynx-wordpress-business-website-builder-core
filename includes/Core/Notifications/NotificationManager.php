<?php

namespace BusinessBuilderCore\Core\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central notification dispatcher.
 *
 * Responsibilities:
 *   - hold registered channels (email, dashboard, ...);
 *   - dispatch a Notification to every channel;
 *   - enforce idempotency via a per-site dedupe log;
 *   - store dashboard notifications per site (Multisite-isolated).
 *
 * Business logic (which event fires which notification) is expressed by
 * callers building a Notification; adding a new channel never requires
 * changing that logic (spec 3 / 37).
 */
class NotificationManager {

    /**
     * Site option holding the dashboard notification list.
     */
    private const OPTION_FEED = 'bb_notifications_feed';

    /**
     * Site option holding processed dedupe keys.
     */
    private const OPTION_DEDUPE = 'bb_notifications_dedupe';

    /**
     * Max dedupe keys remembered per site.
     */
    private const DEDUPE_LIMIT = 500;

    /**
     * Max dashboard notifications kept per site.
     */
    private const FEED_LIMIT = 200;

    /**
     * Registered channels.
     *
     * @var array<string, NotificationChannel>
     */
    protected array $channels = array();

    /**
     * Constructor.
     */
    public function __construct() {

        /*
         * Built-in channels. Email + dashboard are sufficient for this
         * phase (spec 3). Further channels (sms, whatsapp, push) can be
         * registered later without touching this class's logic.
         */
        $this->register_channel( new EmailNotificationChannel() );

        /**
         * Allow extensions to register additional notification channels.
         *
         * @param NotificationManager $manager This manager.
         */
        do_action( 'bb_register_notification_channels', $this );
    }

    /**
     * Register a channel.
     *
     * @param NotificationChannel $channel Channel.
     */
    public function register_channel( NotificationChannel $channel ): void {

        $this->channels[ $channel->get_id() ] = $channel;
    }

    /**
     * All registered channels.
     *
     * @return array<string, NotificationChannel>
     */
    public function channels(): array {

        return $this->channels;
    }

    /**
     * Dispatch a notification to all channels.
     *
     * Returns true when the notification was newly processed, false when
     * it was suppressed as a duplicate (spec 14).
     *
     * @param Notification $notification Notification.
     * @return bool
     */
    public function dispatch( Notification $notification ): bool {

        if ( ! $this->claim( $notification ) ) {
            return false;
        }

        /* Always store in the dashboard feed. */
        $this->store( $notification );

        foreach ( $this->channels as $channel ) {
            $channel->send( $notification );
        }

        /**
         * Fires after a notification has been dispatched.
         *
         * @param Notification $notification The notification.
         */
        do_action( 'bb_notification_dispatched', $notification );

        return true;
    }

    /**
     * Claim a dedupe key for a notification.
     *
     * @param Notification $notification Notification.
     * @return bool True if newly claimed, false if already processed.
     */
    protected function claim( Notification $notification ): bool {

        $key = $notification->dedupe_key();

        if ( '' === $key ) {
            /* No key => cannot dedupe; treat as new. */
            return true;
        }

        /* Scope the key to the current site so Multisite never leaks. */
        $key = get_current_blog_id() . ':' . $key;

        $seen = get_option( self::OPTION_DEDUPE, array() );

        if ( ! is_array( $seen ) ) {
            $seen = array();
        }

        if ( isset( $seen[ $key ] ) ) {
            return false;
        }

        $seen[ $key ] = time();

        /* Trim old keys to keep the option small. */
        if ( count( $seen ) > self::DEDUPE_LIMIT ) {
            asort( $seen );
            $seen = array_slice( $seen, -self::DEDUPE_LIMIT, null, true );
        }

        update_option( self::OPTION_DEDUPE, $seen, false );

        return true;
    }

    /**
     * Store a notification in the dashboard feed.
     *
     * @param Notification $notification Notification.
     */
    protected function store( Notification $notification ): void {

        $feed = get_option( self::OPTION_FEED, array() );

        if ( ! is_array( $feed ) ) {
            $feed = array();
        }

        /*
         * Persist enough context to render the notification centre WITHOUT
         * a second lookup: the category filter, the related entity, the
         * human reference, the payment facts and the target entity id.
         * Everything stored here is public/non-sensitive.
         */
        $data = is_array( $notification->data() ) ? $notification->data() : array();

        $feed[] = array(
            'id'          => uniqid( 'n', true ),
            'event'       => $notification->event(),
            'subject'     => $notification->subject(),
            'message'     => $notification->message(),
            'object_id'   => $notification->object_id(),
            'read'        => false,
            'time'        => time(),
            'category'    => isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : self::category_for( (string) $notification->event() ),
            'entity_type' => isset( $data['entity_type'] ) ? sanitize_key( (string) $data['entity_type'] ) : '',
            'entity_id'   => isset( $data['entity_id'] ) ? absint( $data['entity_id'] ) : (int) $notification->object_id(),
            'reference'   => isset( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '',
            'amount'      => isset( $data['amount'] ) ? sanitize_text_field( (string) $data['amount'] ) : '',
            'currency'    => isset( $data['currency'] ) ? sanitize_text_field( (string) $data['currency'] ) : '',
            'gateway'     => isset( $data['gateway'] ) ? sanitize_key( (string) $data['gateway'] ) : '',
            'customer'    => isset( $data['customer'] ) ? sanitize_text_field( (string) $data['customer'] ) : '',
        );

        if ( count( $feed ) > self::FEED_LIMIT ) {
            $feed = array_slice( $feed, -self::FEED_LIMIT );
        }

        update_option( self::OPTION_FEED, $feed, false );
    }

    /**
     * Map an event slug to a filter category.
     *
     * @param string $event Event slug.
     * @return string consultation|appointment|payment|system
     */
    public static function category_for( string $event ): string {

        $event = sanitize_key( $event );

        if ( 0 === strpos( $event, 'payment' )) {
            return 'payment';
        }

        if ( 0 === strpos( $event, 'appointment' )) {
            return 'appointment';
        }

        if ( 0 === strpos( $event, 'consultation' )) {
            return 'consultation';
        }

        return 'system';
    }

    /**
     * All stored notifications (raw rows, insertion order).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array {

        $feed = get_option( self::OPTION_FEED, array() );

        if ( ! is_array( $feed )) {
            return array();
        }

        return array_values( $feed );
    }

    /**
     * Whether a stored row matches a category filter.
     *
     * @param array  $item     Row.
     * @param string $category Category filter.
     * @return bool
     */
    public static function matches_category( array $item, string $category ): bool {

        $category = sanitize_key( $category );

        if ( '' === $category || 'all' === $category ) {
            return true;
        }

        if ( 'unread' === $category ) {
            return empty( $item['read'] );
        }

        $row_category = isset( $item['category'] ) && '' !== (string) $item['category']
            ? (string) $item['category']
            : self::category_for( isset( $item['event'] ) ? (string) $item['event'] : '' );

        return $row_category === $category;
    }

    /**
     * Filter stored notifications by category, search term and a day window.
     *
     * @param string $category Category filter.
     * @param string $search   Free-text search (reference/customer).
     * @param int    $days     Only items within the last N days (0 = all).
     * @param int    $limit    Max items.
     * @return array<int, array<string, mixed>>
     */
    public function query( string $category = '', string $search = '', int $days = 0, int $limit = 100 ): array {

        $items = array_reverse( $this->all() );

        $search = trim( $search );
        $cutoff = $days > 0 ? time() - ( $days * DAY_IN_SECONDS ) : 0;
        $needle = strtolower( $search );

        $out = array();

        foreach ( $items as $item ) {

            if ( ! is_array( $item )) {
                continue;
            }

            if ( ! self::matches_category( $item, $category )) {
                continue;
            }

            if ( $cutoff > 0 && (int) ( $item['time'] ?? 0 ) < $cutoff ) {
                continue;
            }

            if ( '' !== $needle ) {
                $haystack = strtolower(
                    (string) ( $item['subject'] ?? '' ) . ' '
                    . (string) ( $item['message'] ?? '' ) . ' '
                    . (string) ( $item['reference'] ?? '' ) . ' '
                    . (string) ( $item['amount'] ?? '' ) . ' '
                    . (string) ( $item['customer'] ?? '' )
                );

                if ( false === strpos( $haystack, $needle )) {
                    continue;
                }
            }

            $out[] = $item;

            if ( count( $out ) >= max( 1, $limit )) {
                break;
            }
        }

        return $out;
    }

    /**
     * Get dashboard notifications (newest first).
     *
     * @param int $limit Max items.
     * @return array<int, array<string, mixed>>
     */
    public function feed( int $limit = 20 ): array {

        $feed = get_option( self::OPTION_FEED, array() );

        if ( ! is_array( $feed ) ) {
            return array();
        }

        return array_slice( array_reverse( $feed ), 0, max( 1, $limit ) );
    }

    /**
     * Count unread notifications.
     *
     * @return int
     */
    public function unread_count(): int {

        $count = 0;

        foreach ( $this->feed( self::FEED_LIMIT ) as $item ) {
            if ( empty( $item['read'] ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Mark one notification read.
     *
     * @param string $id Notification id.
     */
    public function mark_read( string $id ): void {

        $this->update_read( array( $id ) );
    }

    /**
     * Mark every notification read.
     */
    public function mark_all_read(): void {

        $this->update_read( null );
    }

    /**
     * Update read flags.
     *
     * @param string[]|null $ids Ids to mark, or null for all.
     */
    protected function update_read( ?array $ids ): void {

        $feed = get_option( self::OPTION_FEED, array() );

        if ( ! is_array( $feed ) ) {
            return;
        }

        foreach ( $feed as $index => $item ) {

            if ( null === $ids || in_array( $item['id'] ?? '', $ids, true ) ) {
                $feed[ $index ]['read'] = true;
            }
        }

        update_option( self::OPTION_FEED, $feed, false );
    }
}
