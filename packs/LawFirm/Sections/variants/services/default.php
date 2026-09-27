<?php
/**
 * Section variant: Legal Services â€” default (grid).
 *
 * Byte-for-byte the pre-Phase-11 layout (columns grid of service cards).
 *
 * @package BusinessBuilderCore\Packs\LawFirm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$columns = isset( $args['columns'] ) ? absint( $args['columns'] ) : 3;
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-services-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

foreach ( $items as $item ) {
	bb_component( 'service/card', is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';