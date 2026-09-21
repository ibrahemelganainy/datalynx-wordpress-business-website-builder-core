<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Effective availability configuration for a booking.
 *
 * Immutable value object answering "when can a customer book?" for one
 * booking section. It merges two sources, in priority order:
 *
 *   1. The booking section's own settings (Page Builder metabox).
 *   2. The site-wide appointment defaults (bb_site_settings), used for any
 *      value the section left empty.
 *
 * All values are validated up front: an invalid time or out-of-range number
 * falls back to the site default rather than producing a broken schedule.
 */
final class AvailabilityConfig {

    /** @var int[] Working-day numbers (0=Sunday .. 6=Saturday). */
    private array $days;

    /** @var string Daily window start (H:i). */
    private string $start;

    /** @var string Daily window end (H:i). */
    private string $end;

    /** @var int Appointment length in minutes. */
    private int $slot;

    /** @var int Buffer between appointments in minutes. */
    private int $buffer;

    /** @var int Hard cap on bookings per day (0 = unlimited). */
    private int $max_per_day;

    /**
     * Private constructor.
     *
     * @param int[]  $days        Working days.
     * @param string $start       H:i start.
     * @param string $end         H:i end.
     * @param int    $slot        Slot minutes.
     * @param int    $buffer      Buffer minutes.
     * @param int    $max_per_day Per-day cap (0 = unlimited).
     */
    private function __construct(
        array $days,
        string $start,
        string $end,
        int $slot,
        int $buffer,
        int $max_per_day
    ) {
        $this->days        = $days;
        $this->start       = $start;
        $this->end         = $end;
        $this->slot        = $slot;
        $this->buffer      = $buffer;
        $this->max_per_day = $max_per_day;
    }

    /**
     * Build the effective config from a booking section's settings.
     *
     * @param array<string, mixed> $settings Section settings.
     * @param array<string, mixed> $defaults Site defaults from the Availability service.
     * @return self
     */
    public static function from_section( array $settings, array $defaults ): self {

        $days = self::parse_days( $settings['availability_days'] ?? null );

        if ( empty( $days ) ) {
            $days = self::parse_days( $defaults['days'] ?? null );
        }

        if ( empty( $days ) ) {
            $days = array( 1, 2, 3, 4, 5 );
        }

        $start = self::parse_time( $settings['availability_start'] ?? '' );

        if ( '' === $start ) {
            $start = self::parse_time( $defaults['start'] ?? '' );
        }

        $end = self::parse_time( $settings['availability_end'] ?? '' );

        if ( '' === $end ) {
            $end = self::parse_time( $defaults['end'] ?? '' );
        }

        if ( self::to_minutes( $start ) >= self::to_minutes( $end ) ) {
            $start = self::parse_time( $defaults['start'] ?? '' );
            $end   = self::parse_time( $defaults['end'] ?? '' );
        }

        $slot = self::parse_positive_int( $settings['availability_slot'] ?? null );

        if ( null === $slot || $slot <= 0 ) {
            $slot = isset( $defaults['slot'] ) ? (int) $defaults['slot'] : 0;
        }

        if ( $slot <= 0 ) {
            $slot = 60;
        }

        $buffer = self::parse_positive_int( $settings['availability_buffer'] ?? null, true );

        if ( null === $buffer ) {
            $buffer = isset( $defaults['buffer'] ) ? (int) $defaults['buffer'] : 0;
        }

        $max = self::parse_positive_int( $settings['availability_max_per_day'] ?? null, true );

        if ( null === $max ) {
            $max = isset( $defaults['max_per_day'] ) ? (int) $defaults['max_per_day'] : 0;
        }

        return new self(
            $days,
            $start,
            $end,
            $slot,
            max( 0, (int) $buffer ),
            max( 0, (int) $max )
        );
    }

    /**
     * Whether a weekday number accepts appointments.
     *
     * @param int $weekday 0=Sunday .. 6=Saturday.
     * @return bool
     */
    public function is_working_day( int $weekday ): bool {
        return in_array( $weekday, $this->days, true );
    }

    /** @return int[] */
    public function days(): array {
        return $this->days;
    }

    /** @return string[] */
    public function hours(): array {
        return array( $this->start, $this->end );
    }

    /** @return string */
    public function start(): string {
        return $this->start;
    }

    /** @return string */
    public function end(): string {
        return $this->end;
    }

    /** @return int */
    public function slot(): int {
        return $this->slot;
    }

    /** @return int */
    public function buffer(): int {
        return $this->buffer;
    }

    /** @return int */
    public function max_per_day(): int {
        return $this->max_per_day;
    }

    /**
     * Parse a day list into unique weekday numbers (0..6).
     *
     * @param mixed $raw Raw value (array, or comma string).
     * @return int[]
     */
    private static function parse_days( $raw ): array {

        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }

        if ( ! is_array( $raw ) ) {
            return array();
        }

        $days = array();

        foreach ( $raw as $day ) {

            if ( ! is_numeric( $day ) ) {
                continue;
            }

            $day = (int) $day;

            if ( $day < 0 || $day > 6 ) {
                continue;
            }

            $days[ $day ] = $day;
        }

        return array_values( $days );
    }

    /**
     * Parse a HH:MM time ('' when invalid).
     *
     * @param mixed $raw Raw value.
     * @return string
     */
    private static function parse_time( $raw ): string {

        $raw = trim( (string) $raw );

        if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m ) ) {
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
     * Parse a non-negative integer.
     *
     * @param mixed $raw        Raw value.
     * @param bool  $allow_zero When true, 0 is valid and null is returned only when empty.
     * @return int|null
     */
    private static function parse_positive_int( $raw, bool $allow_zero = false ): ?int {

        if ( null === $raw || '' === $raw ) {
            return $allow_zero ? null : 0;
        }

        if ( ! is_numeric( $raw ) ) {
            return $allow_zero ? null : 0;
        }

        $value = (int) $raw;

        if ( $value < 0 ) {
            return $allow_zero ? null : 0;
        }

        return $value;
    }

    /**
     * Minutes since midnight for a H:i time.
     *
     * @param string $time H:i.
     * @return int
     */
    private static function to_minutes( string $time ): int {

        if ( '' === $time ) {
            return 0;
        }

        list( $h, $i ) = array_map( 'intval', explode( ':', $time ) );

        return ( $h * 60 ) + $i;
    }
}
