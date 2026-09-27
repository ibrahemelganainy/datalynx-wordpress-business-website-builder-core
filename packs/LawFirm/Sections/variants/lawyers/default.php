<?php
/**
 * Section variant: Lawyers â€” default (grid).
 *
 * Byte-for-byte the layout of the pre-Phase-11 renderer: a columns grid of
 * lawyer cards. Presentation only â€” $args carries prepared items.
 *
 * Contract (see includes/Builder/section-variants.php):
 *   $args['items']    array<int, array> Prepared args for the 'lawyer/card' component.
 *   $args['columns']  int                Grid columns.
 *
 * @package BusinessBuilderCore\Packs\LawFirm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$columns = isset( $args['columns'] ) ? absint( $args['columns'] ) : 3;
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-lawyers-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

foreach ( $items as $item ) {
	bb_component( 'lawyer/card', is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';