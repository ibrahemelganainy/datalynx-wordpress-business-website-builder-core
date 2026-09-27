<?php
/**
 * Section variant: Doctors — grid (explicit, auto-fit).
 *
 * A genuinely different composition from the fixed-column default: an auto-fit
 * grid that ignores the columns setting and lets the browser pack cards at
 * their natural minimum width. Presentation only — no queries, prepared data.
 *
 * @package BusinessBuilderCore\Packs\Medical
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items        = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$component    = isset( $args['component'] ) ? (string) $args['component'] : 'doctor/card';
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-doctors-grid bb-doctors-grid-auto">';

foreach ( $items as $item ) {
	bb_component( $component, is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';