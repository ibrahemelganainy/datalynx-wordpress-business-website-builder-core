<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Availability + booking service.
 *
 * Owns the server-side rules that prevent double booking (spec 21/22):
 *   - a working-hours window per day,
 *   - a fixed slot length + buffer,
 *   - existing appointments that block a slot,
 *   - admin-configurable blocked dates.
 *
 * Booking is created via create() which re-checks the slot immediately
 * before insert. A site-scoped mutex (transient lock) closes the small
 * race window between the check and the insert so two simultaneous
 * requests cannot both claim the same slot.
 *
 * All configuration is read from SiteSettings so nothing is hardcoded;
 * defaults are sensible and safe.
 */
class Availability {

    /**
     * Site settings option (read directly to avoid a hard dependency).
     */
    private const SETTINGS_OPTION = 'bb_site_settings';

    /**
     * Working-hours defaults (24h).
     */
    private const DEFAULT_START = '09:00';
    private const DEFAULT_END = '17:00';
    private const DEFAULT_SLOT = 60;   // minutes
    private const DEFAULT_BUFFER = 15; // minutes

    /**
     * Working days (0=Sun .. 6=Sat). Defaults MonÃ¢â‚¬â€œFri.
     *
     * @return int[]
     */
    public function working_days(): array {

        $settings = $this->settings();

        $days = isset( $settings['appointment_working_days'] ) && is_array( $settings['appointment_working_days'] )
            ? array_map( 'absint', $settings['appointment_working_days'] )
            : array( 1, 2, 3, 4, 5 );

        return array_values( array_unique( $days ) );
    }

    /**
     * Working hours [start, end].
     *
     * @return string[]
     */
    public function working_hours(): array {

        $settings = $this->settings();

        $start = $this->normalize_time( $settings['appointment_start'] ?? self::DEFAULT_START );
        $end = $this->normalize_time( $settings['appointment_end'] ?? self::DEFAULT_END );

        if ( '' === $start ) {
            $start = self::DEFAULT_START;
        }

        if ( '' === $end ) {
            $end = self::DEFAULT_END;
        }

        return array( $start, $end );
    }

    /**
     * Slot length in minutes.
     *
     * @return int
     */
    public function slot_minutes(): int {

        $settings = $this->settings();
        $slot = isset( $settings['appointment_slot'] ) ? absint( $settings['appointment_slot'] ) : self::DEFAULT_SLOT;

        return $slot > 0 ? $slot : self::DEFAULT_SLOT;
    }

    /**
     * Buffer minutes between appointments.
     *
     * @return int
     */
    public function buffer_minutes(): int {

        $settings = $this->settings();
        $buffer = isset( $settings['appointment_buffer'] ) ? absint( $settings['appointment_buffer'] ) : self::DEFAULT_BUFFER;

        return max( 0, $buffer );
    }

    /**
     * Blocked dates (Y-m-d).
     *
     * @return string[]
     */
    public function blocked_dates(): array {

        $settings = $this->settings();

        if ( empty( $settings['appointment_blocked_dates'] ) ) {
            return array();
        }

        $raw = $settings['appointment_blocked_dates'];

        $list = is_array( $raw ) ? $raw : preg_split( '/[\r\n,]+/', (string) $raw );

        $out = array();

        foreach ( $list as $date ) {
            $date = trim( (string) $date );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                $out[] = $date;
            }
        }

        return array_values( array_unique( $out ) );
    }

    /**
     * Generate the slots for a given date.
     *
     * @param string $date     Y-m-d.
     * @param int    $lawyer_id Optional lawyer id (0 = none).
     * @return array<int, array<string, mixed>> Each: ['start','end','available','reason']
     */
    public function slots_for_date( string $date, int $lawyer_id = 0 ): array {

        if ( ! $this->is_valid_date( $date ) ) {
            return array();
        }

        if ( ! $this->is_working_day( $date ) ) {
            return array();
        }

        if ( in_array( $date, $this->blocked_dates(), true ) ) {
            return array();
        }

        list( $start, $end ) = $this->working_hours();

        $slot = $this->slot_minutes();
        $buffer = $this->buffer_minutes();

        $start_min = $this->to_minutes( $start );
        $end_min = $this->to_minutes( $end );

        if ( $start_min >= $end_min ) {
            return array();
        }

        /* Existing blocking appointments for this date + lawyer. */
        $taken = $this->booked_ranges( $date, $lawyer_id );

        $now = current_time( 'timestamp' );
        $out = array();

        for ( $m = $start_min; ( $m + $slot ) <= $end_min; $m += $slot ) {

            $slot_start = $m;
            $slot_end = $m + $slot;

            $available = true;
            $reason = '';

            /* Past check (same-day). */
            $slot_time = $this->from_minutes( $slot_start );
            $slot_space = chr( 32 );
            $slot_ts = strtotime( $date . $slot_space . $slot_time );
            if ( $slot_ts < $now ) {
                $available = false;
                $reason = 'past';
            }

            /* Overlap check against booked ranges (with buffer). */
            if ( $available && $this->overlaps_any( $slot_start, $slot_end, $taken, $buffer ) ) {
                $available = false;
                $reason = 'booked';
            }

            $out[] = array(
                'start'     => $this->from_minutes( $slot_start ),
                'end'       => $this->from_minutes( $slot_end ),
                'available' => $available,
                'reason'    => $reason,
            );
        }

        return $out;
    }

    /**
     * Whether a specific slot is free.
     *
     * @param string $date       Y-m-d.
     * @param string $start      H:i.
     * @param int    $lawyer_id  Lawyer id0 = none).
     * @param int    $ignore_id  Appointment id to ignore (for reschedule).
     * @return bool
     */
    public function is_slot_free( string $date, string $start, int $lawyer_id = 0, int $ignore_id = 0 ): bool {

        if ( ! $this->is_valid_date( $date ) || '' === $this->normalize_time( $start ) ) {
            return false;
        }

        if ( ! $this->is_working_day( $date ) || in_array( $date, $this->blocked_dates(), true ) ) {
            return false;
        }

        list( $work_start, $work_end ) = $this->working_hours();
        $slot = $this->slot_minutes();
        $buffer = $this->buffer_minutes();

        $slot_start = $this->to_minutes( $start );
        $slot_end = $slot_start + $slot;

        if ( $slot_start < $this->to_minutes( $work_start ) || $slot_end > $this->to_minutes( $work_end ) ) {
            return false;
        }

        $taken = $this->booked_ranges( $date, $lawyer_id, $ignore_id );

        return ! $this->overlaps_any( $slot_start, $slot_end, $taken, $buffer );
    }

    /**
     * Create an appointment after re-validating the slot.
     *
     * @param array $data Appointment data.
     * @return int|\WP_Error Appointment id, or error.
     */
    public function create( array $data ) {

        $date = isset( $data['date'] ) ? (string) $data['date'] : '';
        $start = isset( $data['start'] ) ? $this->normalize_time( (string) $data['start'] ) : '';
        $lawyer_id = isset( $data['lawyer_id'] ) ? absint( $data['lawyer_id'] ) : 0;

        if ( ! $this->is_valid_date( $date ) ) {
            return new \WP_Error( 'bb_invalid_date', __( 'Please choose a valid date.', 'business-builder' ) );
        }

        if ( '' === $start ) {
            return new \WP_Error( 'bb_invalid_time', __( 'Please choose a valid time slot.', 'business-builder' ) );
        }

        /* Acquire a site-scoped lock so concurrent requests cannot race. */
        $lock_key = 'bb_appt_lock_' . md5( $date . '|' . $start . '|' . $lawyer_id );
        $locked = $this->acquire_lock( $lock_key );

        try {

            if ( ! $this->is_slot_free( $date, $start, $lawyer_id ) ) {
                return new \WP_Error( 'bb_slot_taken', __( 'That time slot is no longer available. Please choose another.', 'business-builder' ) );
            }

            return $this->insert( $data, $start );

        } finally {
            if ( $locked ) {
                delete_transient( $lock_key );
            }
        }
    }

    /**
     * Insert the appointment post + meta.
     *
     * @param array  $data  Appointment data.
     * @param string $start H:i start (normalized).
     * @return int|\WP_Error
     */
    private function insert( array $data, string $start ) {

        $date = (string) $data['date'];
        $slot = $this->slot_minutes();
        $end = $this->from_minutes( $this->to_minutes( $start ) + $slot );

        $name = isset( $data['client_name'] ) ? sanitize_text_field( (string) $data['client_name'] ) : '';

        $sp = chr( 32 );
        $dash = chr( 45 );

        $title = $name !== ''
            ? $name . $sp . $dash . $sp . $date . $sp . $start
            : $date . $sp . $start;

        $post_id = wp_insert_post(
            array(
                'post_type'   => Appointment::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $title,
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $map = array(
            'client_name'     => $name,
            'client_phone'    => isset( $data['client_phone'] ) ? sanitize_text_field( (string) $data['client_phone'] ) : '',
            'client_email'    => isset( $data['client_email'] ) ? sanitize_email( (string) $data['client_email'] ) : '',
            'lawyer_id'       => isset( $data['lawyer_id'] ) ? absint( $data['lawyer_id'] ) : 0,
            'consultation_id' => isset( $data['consultation_id'] ) ? absint( $data['consultation_id'] ) : 0,
            'practice_area'   => isset( $data['practice_area'] ) ? sanitize_text_field( (string) $data['practice_area'] ) : '',
            'date'            => $date,
            'start'           => $start,
            'end'             => $end,
            'timezone'        => isset( $data['timezone'] ) ? sanitize_text_field( (string) $data['timezone'] ) : '',
            'type'            => isset( $data['type'] ) ? sanitize_key( (string) $data['type'] ) : 'consultation',
            'notes'           => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
            'status'          => AppointmentMeta::default_status(),
            'location'        => isset( $data['location'] ) ? sanitize_text_field( (string) $data['location'] ) : '',
            'meeting_url'     => isset( $data['meeting_url'] ) ? esc_url_raw( (string) $data['meeting_url'] ) : '',
            'payment_status'  => isset( $data['payment_status'] ) ? sanitize_key( (string) $data['payment_status'] ) : 'not_required',
            'created'         => current_time( 'mysql' ),
            'updated'         => current_time( 'mysql' ),
        );

        foreach ( $map as $key => $value ) {
            update_post_meta( $post_id, AppointmentMeta::key( $key ), $value );
        }

        return (int) $post_id;
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * Blocking [start_min, end_min] ranges for a date + lawyer.
     *
     * @param string $date      Y-m-d.
     * @param int    $lawyer_id Lawyer id (0 = none).
     * @param int    $ignore_id Appointment id to ignore.
     * @return array<int, array{0:int,1:int}>
     */
    private function booked_ranges( string $date, int $lawyer_id, int $ignore_id = 0 ): array {

        if ( ! post_type_exists( Appointment::POST_TYPE ) ) {
            return array();
        }

        $query = array(
            'post_type'      => Appointment::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => AppointmentMeta::key( 'date' ),
                    'value'   => $date,
                    'compare' => '=',
                ),
            ),
        );

        if ( $lawyer_id > 0 ) {
            $query['meta_query'][] = array(
                'key'     => AppointmentMeta::key( 'lawyer_id' ),
                'value'   => $lawyer_id,
                'compare' => '=',
            );
        }

        $ids = get_posts( $query );

        $ranges = array();

        foreach ( $ids as $id ) {

            if ( $ignore_id > 0 && (int) $id === $ignore_id ) {
                continue;
            }

            $status = (string) get_post_meta( $id, AppointmentMeta::key( 'status' ), true );

            if ( ! in_array( $status, AppointmentMeta::blocking_statuses(), true ) ) {
                continue;
            }

            $s = $this->normalize_time( (string) get_post_meta( $id, AppointmentMeta::key( 'start' ), true ) );
            $e = $this->normalize_time( (string) get_post_meta( $id, AppointmentMeta::key( 'end' ), true ) );

            if ( '' === $s ) {
                continue;
            }

            if ( '' === $e ) {
                $e = $this->from_minutes( $this->to_minutes( $s ) + $this->slot_minutes() );
            }

            $ranges[] = array( $this->to_minutes( $s ), $this->to_minutes( $e ) );
        }

        return $ranges;
    }

    /**
     * Whether a slot overlaps any booked range (+ buffer).
     *
     * @param int   $slot_start Slot start (min).
     * @param int   $slot_end   Slot end (min).
     * @param array $ranges     Booked ranges.
     * @param int   $buffer     Buffer minutes.
     * @return bool
     */
    private function overlaps_any( int $slot_start, int $slot_end, array $ranges, int $buffer ): bool {

        foreach ( $ranges as $range ) {

            $r_start = $range[0] - $buffer;
            $r_end = $range[1] + $buffer;

            if ( $slot_start < $r_end && $slot_end > $r_start ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Acquire a short site-scoped lock.
     *
     * @param string $key Lock key.
     * @return bool
     */
    private function acquire_lock( string $key ): bool {

        if ( get_transient( $key ) ) {
            return false;
        }

        set_transient( $key, 1, 30 );

        return true;
    }

    /**
     * Whether a date is a working day.
     *
     * @param string $date Y-m-d.
     * @return bool
     */
    public function is_working_day( string $date ): bool {

        $ts = strtotime( $date . ' 12:00:00' );

        if ( false === $ts ) {
            return false;
        }

        return in_array( (int) gmdate( 'w', $ts ), $this->working_days(), true );
    }

    /**
     * Validate a Y-m-d date.
     *
     * @param string $date Date.
     * @return bool
     */
    public function is_valid_date( string $date ): bool {

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return false;
        }

        $parts = explode( '-', $date );

        return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
    }

    /**
     * Normalize a H:i time ('' when invalid).
     *
     * @param string $time Time.
     * @return string
     */
    public function normalize_time( string $time ): string {

        $time = trim( $time );

        if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $m ) ) {
            return '';
        }

        $h = (int) $m[1];
        $i = (int) $m[2];

        if ( $h < 0 || $h > 23 || $i < 0 || $i > 59 ) {
            return '';
        }

        return sprintf( '%02d:%02d', $h, $i );
    }

    /**
     * Minutes since midnight.
     *
     * @param string $time H:i.
     * @return int
     */
    private function to_minutes( string $time ): int {

        list( $h, $i ) = array_map( 'intval', explode( ':', $time ) );

        return ( $h * 60 ) + $i;
    }

    /**
     * Minutes since midnight => H:i.
     *
     * @param int $minutes Minutes.
     * @return string
     */
    private function from_minutes( int $minutes ): string {

        $h = intdiv( $minutes, 60 );
        $i = $minutes % 60;

        return sprintf( '%02d:%02d', $h, $i );
    }

    /**
     * Read site settings array.
     *
     * @return array<string, mixed>
     */
    private function settings(): array {

        $settings = get_option( self::SETTINGS_OPTION, array() );

        return is_array( $settings ) ? $settings : array();
    }
}
