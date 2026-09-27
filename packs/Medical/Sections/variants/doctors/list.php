<?php
/**
 * Section variant: Doctors — list.
 *
 * A stacked directory list (one doctor per row) rather than a columns grid.
 * Pairs naturally with the horizontal doctor card. Presentation only — no
 * queries, prepared data.
 *
 * @package BusinessBuilderCore\Packs\Medical
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items        = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$component    = isset( $args['component'] ) ? (string) $args['component'] : 'doctor/card';
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-doctors-list">';

foreach ( $items as $item ) {
	bb_component( $component, is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';