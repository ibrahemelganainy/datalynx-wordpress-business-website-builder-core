<?php
/**
 * Section variant: Medical Services — default (grid).
 *
 * Presentation only — $args carries prepared items. No queries, no business
 * logic (Phase 11 §4-§5 contract).
 *
 * @package BusinessBuilderCore\Packs\Medical
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items        = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$columns      = isset( $args['columns'] ) ? absint( $args['columns'] ) : 3;
$component    = isset( $args['component'] ) ? (string) $args['component'] : 'medical-service/card';
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-medical-services-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

foreach ( $items as $item ) {
	bb_component( $component, is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';