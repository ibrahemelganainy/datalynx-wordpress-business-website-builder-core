<?php
/**
 * Section variant: Practice Areas â€” list (single column).
 *
 * Same items, same order, same component; only the wrapper changes.
 *
 * @package BusinessBuilderCore\Packs\LawFirm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-practice-areas-list">';

foreach ( $items as $item ) {
	bb_component( 'practice-area/card', is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';