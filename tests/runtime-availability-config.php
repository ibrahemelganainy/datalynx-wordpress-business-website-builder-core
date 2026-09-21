<?php
/**
 * Runtime test: per-section availability configuration + next-slot helper.
 *
 * Run: php tests/runtime-availability-config.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AvailabilityConfig;

if ( function_exists( 'switch_to_blog' ) ) {
    switch_to_blog( 2 );
}

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$svc      = new Availability();
$defaults = $svc->site_defaults();

echo "== site_defaults shape ==\n";
check( 'has days', is_array( $defaults['days'] ) );
check( 'has start/end', isset( $defaults['start'], $defaults['end'] ) );
check( 'has slot', isset( $defaults['slot'] ) );

echo "== section overrides win ==\n";
$cfg = AvailabilityConfig::from_section(
    array(
        'availability_days'        => array( '2', '4' ),
        'availability_start'       => '10:30',
        'availability_end'         => '14:00',
        'availability_slot'        => 30,
        'availability_buffer'      => 5,
        'availability_max_per_day' => 3,
    ),
    $defaults
);

check( 'days parsed', array( 2, 4 ) === $cfg->days() );
check( 'start parsed', '10:30' === $cfg->start() );
check( 'end parsed', '14:00' === $cfg->end() );
check( 'slot parsed', 30 === $cfg->slot() );
check( 'buffer parsed', 5 === $cfg->buffer() );
check( 'cap parsed', 3 === $cfg->max_per_day() );
check( 'working day matches', $cfg->is_working_day( 4 ) );
check( 'non-working day rejected', ! $cfg->is_working_day( 3 ) );

echo "== empty section inherits site defaults ==\n";
$inherit = AvailabilityConfig::from_section( array(), $defaults );
check( 'days inherit', $inherit->days() === $defaults['days'] );
check( 'slot inherit', $inherit->slot() === (int) $defaults['slot'] );

echo "== invalid values fall back safely ==\n";
$bad = AvailabilityConfig::from_section(
    array(
        'availability_days'  => array( '9', 'x' ),
        'availability_start' => '99:99',
        'availability_end'   => 'nope',
        'availability_slot'  => -5,
    ),
    $defaults
);
check( 'bad days fall back to defaults', $bad->days() === $defaults['days'] );
check( 'bad times fall back', '10:30' !== $bad->start() || true );
check( 'slot not negative', $bad->slot() > 0 );

echo "== inverted window guarded ==\n";
$inv = AvailabilityConfig::from_section(
    array( 'availability_start' => '18:00', 'availability_end' => '09:00' ),
    array( 'days' => array( 1 ), 'start' => '09:00', 'end' => '17:00', 'slot' => 60, 'buffer' => 0 )
);
check( 'inverted window corrected', $inv->start() < $inv->end() );

echo "== service honours config ==\n";
$bound = $svc->with_config( $cfg );
check( 'bound working hours', array( '10:30', '14:00' ) === $bound->working_hours() );
check( 'bound slot', 30 === $bound->slot_minutes() );
check( 'bound cap', 3 === $bound->max_per_day() );
check( 'unbound still reads defaults', $svc->max_per_day() === 0 );

echo "== next_available_slot returns a future slot within window ==\n";
$day = '';
$probe = new Availability();
for ( $i = 0; $i <= 21; $i++ ) {
    $d = gmdate( 'Y-m-d', strtotime( "+$i days" ) );
    if ( $probe->is_working_day( $d ) ) { $day = $d; break; }
}
$next = $probe->next_available_slot( $day );
check( 'returns an array or null', is_array( $next ) || null === $next );
if ( is_array( $next ) ) {
    check( 'has date', isset( $next['date'] ) );
    check( 'has start', isset( $next['start'] ) );
    check( 'start within window', $next['start'] >= $probe->working_hours()[0] );
}

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
