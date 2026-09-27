<?php
/**
 * Section variant: Lawyers â€” featured.
 *
 * The FIRST item is presented as a full-width featured card; the remaining
 * items keep the standard card in a columns grid. This is a pure PRESENTATION
 * arrangement: it reorders nothing and uses the existing item order (the
 * first item is the first returned item). No "featured" metadata is invented.
 *
 * @package BusinessBuilderCore\Packs\LawFirm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$columns = isset( $args['columns'] ) ? absint( $args['columns'] ) : 3;

if ( empty( $items ) ) {
	echo '<div class="bb-lawyers-grid bb-grid-columns-' . esc_attr( $columns ) . '"></div>';
	return;
}

$featured = array_shift( $items );
$rest     = $items;

$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-lawyers-featured">';

if ( is_array( $featured ) ) {
	/*
	 * Phase-11 behaviour is preserved: the first (featured) card keeps the
	 * 'featured' card variant (which resolves to the base card when no
	 * card-featured.php exists). The section's card_variant applies to the
	 * remaining grid cards, so the two dimensions stay independent.
	 */
	bb_component( 'lawyer/card', $featured, 'featured' );
}

if ( ! empty( $rest ) ) {
	echo '<div class="bb-lawyers-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

	foreach ( $rest as $item ) {
		bb_component( 'lawyer/card', is_array( $item ) ? $item : array(), $card_variant );
	}

	echo '</div>';
}

echo '</div>';