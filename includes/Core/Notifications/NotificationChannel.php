<?php

namespace BusinessBuilderCore\Core\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * A notification delivery channel (email, dashboard, sms, ...).
 *
 * Channels are intentionally tiny: they receive a Notification and
 * deliver it. Business logic (which events produce which notifications)
 * lives in NotificationManager, so new channels never require touching
 * that logic.
 */
interface NotificationChannel {

    /**
     * Stable channel id (e.g. 'email', 'dashboard').
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Deliver a notification.
     *
     * @param Notification $notification Notification to deliver.
     * @return bool Whether delivery succeeded.
     */
    public function send( Notification $notification ): bool;
}
