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

        $feed[] = array(
            'id'        => uniqid( 'n', true ),
            'event'     => $notification->event(),
            'subject'   => $notification->subject(),
            'message'   => $notification->message(),
            'object_id' => $notification->object_id(),
            'read'      => false,
            'time'      => time(),
        );

        if ( count( $feed ) > self::FEED_LIMIT ) {
            $feed = array_slice( $feed, -self::FEED_LIMIT );
        }

        update_option( self::OPTION_FEED, $feed, false );
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
