<?php
/**
 * Runtime test: booking form renders the availability summary and the
 * next-available-slot hint from the section's structured settings.
 *
 * Run: php tests/runtime-booking-form-render.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\AvailabilityFactory;

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

/*
 * Render the booking form template with a controlled section settings array.
 * The template expects: $bb_payment, $bb_section_id, $bb_page_id,
 * $bb_availability, $bb_avail_svc.
 */
function bb_render_booking_form( array $settings ): string {
    $bb_payment      = array( 'payable' => false );
    $bb_section_id   = 'test-section';
    $bb_page_id      = 0;
    $bb_availability = AvailabilityFactory::config( $settings );
    $bb_avail_svc    = ( new \BusinessBuilderCore\Packs\LawFirm\Appointments\Availability() )->with_config( $bb_availability );

    ob_start();
    include BB_CORE_PATH . 'templates/booking-form.php';
    return (string) ob_get_clean();
}

echo "== availability summary renders ==\n";
$html = bb_render_booking_form(
    array(
        'availability_days'  => array( '1', '3', '5' ),
        'availability_start' => '09:00',
        'availability_end'   => '13:00',
        'availability_slot'  => 45,
    )
);

check( 'availability panel present', false !== strpos( $html, 'bb-booking-availability' ) );
check( 'shows Monday', false !== strpos( $html, 'Monday' ) );
check( 'shows Wednesday', false !== strpos( $html, 'Wednesday' ) );
check( 'shows Friday', false !== strpos( $html, 'Friday' ) );
check( 'shows the start time', false !== strpos( $html, '09:00' ) );
check( 'shows the end time', false !== strpos( $html, '13:00' ) );
check( 'shows the slot length', false !== strpos( $html, '45' ) );
check( 'does not list a non-working day (Tuesday)', false === strpos( $html, 'Tuesday' ) );

echo "== next-available-slot hint on a taken-slot state ==\n";
$_GET['bb_booking']  = 'taken';
$_GET['bb_try_date'] = (string) current_time( 'Y-m-d' );
$taken_html = bb_render_booking_form(
    array(
        'availability_days'  => array( '1', '2', '3', '4', '5' ),
        'availability_start' => '09:00',
        'availability_end'   => '17:00',
        'availability_slot'  => 60,
    )
);
unset( $_GET['bb_booking'], $_GET['bb_try_date'] );

check( 'shows the taken notice', false !== strpos( $taken_html, 'just taken' ) );
check( 'shows a next-available-time hint', false !== strpos( $taken_html, 'Next available time' ) );

echo "== no PHP notice/warning leaked into output ==\n";
$has_notice = ( false !== stripos( $html, 'Notice:' )) || ( false !== stripos( $html, 'Warning:' )) || ( false !== stripos( $html, 'Fatal error' ));
check( 'output is clean', ! $has_notice );
check( 'taken-state output is clean', false === stripos( $taken_html, 'Fatal error' ) );

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
