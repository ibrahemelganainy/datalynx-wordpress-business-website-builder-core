<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

if ( ! defined( 'ABSPATH' ) ) {
}

/**
 * Appointment data model: statuses, meta keys, and shared constants.
 *
 * Single source of truth for the appointment subsystem so the booking
 * form, the availability service and the admin UI agree on keys/values.
 *
 * Meta keys (all prefixed _bb_appointment_):
 *   client_name, client_phone, client_email,
 *   consultation_id, practice_area,
 *   date (Y-m-d), start (H:i), end (H:i), timezone,
 *   type, notes, status, location, meeting_url,
 *   payment_status, created, updated
 */
class AppointmentMeta {

    /**
     * Statuses (appointment status is SEPARATE from payment status).
     *
     * @return array<string, string>
     */
    public static function statuses(): array {

        $statuses = array(
            'pending'     => __( 'Pending', 'business-builder' ),
            'confirmed'   => __( 'Confirmed', 'business-builder' ),
            'completed'   => __( 'Completed', 'business-builder' ),
            'cancelled'   => __( 'Cancelled', 'business-builder' ),
            'no_show'     => __( 'No Show', 'business-builder' ),
            'rescheduled' => __( 'Rescheduled', 'business-builder' ),
        );

        /**
         * Allow extensions to register additional appointment statuses.
         *
         * @param array<string, string> $statuses slug => label.
         */
        return apply_filters( 'bb_appointment_statuses', $statuses );
    }

    /**
     * Default status.
     */
    public static function default_status(): string {
        return 'pending';
    }

    /**
     * Human label for a status.
     *
     * @param string $status Status slug.
     * @return string
     */
    public static function status_label( string $status ): string {

        $statuses = self::statuses();

        return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
    }

    /**
     * Full meta key for a short name.
     *
     * @param string $name Short name.
     * @return string
     */
    public static function key( string $name ): string {

        return '_bb_appointment_' . sanitize_key( $name );
    }

    /**
     * Statuses that occupy a time slot (block re-booking).
     *
     * Cancelled / no-show slots are freed.
     *
     * @return string[]
     */
    public static function blocking_statuses(): array {

        return array( 'pending', 'confirmed', 'completed', 'rescheduled' );
    }

    /**
     * Appointment type labels.
     *
     * @return array<string, string>
     */
    public static function types(): array {

        $types = array(
            'consultation' => __( 'Consultation', 'business-builder' ),
            'meeting'      => __( 'Meeting', 'business-builder' ),
            'court'        => __( 'Court Session', 'business-builder' ),
        );

        /**
         * Allow extensions to register additional appointment types.
         *
         * @param array<string, string> $types slug => label.
         */
        return apply_filters( 'bb_appointment_types', $types );
    }

    /**
     * Human label for a type.
     *
     * @param string $type Type slug.
     * @return string
     */
    public static function type_label( string $type ): string {

        $types = self::types();

        return isset( $types[ $type ] ) ? $types[ $type ] : $type;
    }
}
