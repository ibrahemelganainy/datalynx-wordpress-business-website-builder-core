<?php
/**
 * Section variant: Lawyers â€” list.
 *
 * A single-column list of the SAME lawyer cards (same items, same order, same
 * component). Only the wrapper/grid changes, so the card markup is never
 * duplicated.
 *
 * @package BusinessBuilderCore\Packs\LawFirm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$card_variant = isset( $args['component_variant'] ) ? (string) $args['component_variant'] : '';

echo '<div class="bb-lawyers-list">';

foreach ( $items as $item ) {
	bb_component( 'lawyer/card', is_array( $item ) ? $item : array(), $card_variant );
}

echo '</div>';