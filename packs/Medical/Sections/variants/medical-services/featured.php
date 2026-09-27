<?php
/**
 * Section variant: Medical Services — featured.
 *
 * A genuinely different composition: the first service is given a wide hero
 * card and the remainder flow beneath it in a compact (auto-fit) grid. Derived
 * purely from the same prepared item list — no queries, no business logic.
 *
 * @package BusinessBuilderCore\Packs\Medical
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items        = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$component    = isset( $args['component'] ) ? (string) $args['component'] : 'medical-service/card';
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

$lead  = array();
$rest  = array();

foreach ( $items as $index => $item ) {

	if ( 0 === $index ) {
		$lead[] = $item;
		continue;
	}

	$rest[] = $item;
}

echo '<div class="bb-medical-services-featured">';

if ( ! empty( $lead ) ) {

	echo '<div class="bb-medical-services-lead">';

	foreach ( $lead as $item ) {
		bb_component( $component, is_array( $item ) ? $item : array(), $card_variant );
	}

	echo '</div>';
}

if ( ! empty( $rest ) ) {

	echo '<div class="bb-medical-services-rest bb-medical-services-grid-auto">';

	foreach ( $rest as $item ) {
		bb_component( $component, is_array( $item ) ? $item : array(), $card_variant );
	}

	echo '</div>';
}

echo '</div>';