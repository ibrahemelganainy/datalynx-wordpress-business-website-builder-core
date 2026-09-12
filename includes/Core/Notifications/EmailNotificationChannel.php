<?php

namespace BusinessBuilderCore\Core\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Delivers notifications by email via wp_mail().
 *
 * Uses the recipient supplied on the notification, or the site admin
 * email when none is given. Headers are sanitized to avoid email
 * header injection (spec 31).
 */
class EmailNotificationChannel implements NotificationChannel {

    /**
     * Channel id.
     */
    public function get_id(): string {
        return 'email';
    }

    /**
     * Deliver by email.
     *
     * @param Notification $notification Notification.
     * @return bool
     */
    public function send( Notification $notification ): bool {

        $to = $notification->recipient();


        if ( '' === $to ) {
            $bb_settings = get_option( 'bb_site_settings', array() );
            $bb_inbox = is_array( $bb_settings )
                ? (string) ( $bb_settings['notification_email'] ?? '' )
                : '';

            if ( '' !== $bb_inbox ) {
                $to = $bb_inbox;
            }
        }

        if ( '' === $to || ! is_email( $to ) ) {
            $to = get_option( 'admin_email' );
        }

        if ( ! is_email( $to ) ) {
            return false;
        }

        $subject = $notification->subject();

        $body = $notification->message();

        /*
         * Never allow a newline in the subject (header injection).
         */
        $subject = str_replace(
            array( "\r", "\n" ),
            " ",
            $subject
        );

        return (bool) wp_mail( $to, $subject, $body );
    }
}
