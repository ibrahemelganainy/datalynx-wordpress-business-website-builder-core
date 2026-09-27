<?php
/**
 * Section variant: Doctors — default (grid).
 *
 * Presentation only — $args carries prepared items. This template performs NO
 * query and no business logic (Phase 11 §4-§5 contract).
 *
 * Contract (see includes/Builder/section-variants.php):
 *   $args['items']              array<int, array> Prepared args for 'doctor/card'.
 *   $args['columns']            int                Grid columns.
 *   $args['component']          string             Component path.
 *   $args['component_variant']  string             Resolved card variant.
 *
 * @package BusinessBuilderCore\Packs\Medical
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items           = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$columns         = isset( $args['columns'] ) ? absint( $args['columns'] ) : 3;
$component       = isset( $args['component'] ) ? (string) $args['component'] : 'doctor/card';
$card_variant    = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-doctors-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

foreach ( $items as $item ) {
	bb_component( $component, is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';