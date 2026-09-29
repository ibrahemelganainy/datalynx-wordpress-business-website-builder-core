<?php
/**
 * Services — featured layout.
 *
 * The first service is emphasised (it spans the grid); the rest follow. The
 * emphasis is expressed with a class, so the visual weight is a CSS decision and
 * the markup stays single-sourced.
 *
 * @package BusinessBuilderCore
 *
 * @var array $args Prepared presentation data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_items   = isset( $args['items'] ) && is_array( $args['items'] ) ? array_values( $args['items'] ) : array();
$bb_columns = isset( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3;
$bb_style   = isset( $args['style'] ) ? sanitize_key( (string) $args['style'] ) : 'elevated';

$bb_featured = ! empty( $bb_items ) ? array( $bb_items[0] ) : array();
$bb_rest     = count( $bb_items ) > 1 ? array_slice( $bb_items, 1 ) : array();
?>
<div class="bb-services bb-services--featured bb-services--<?php echo esc_attr( $bb_style ); ?>">
	<?php if ( ! empty( $args['content']['title'] ) || ! empty( $args['content']['description'] ) ) : ?>
		<div class="bb-section-heading">
			<?php if ( ! empty( $args['content']['title'] ) ) : ?>
				<h2 class="bb-section-title"><?php echo esc_html( (string) $args['content']['title'] ); ?></h2>
			<?php endif; ?>
			<?php if ( ! empty( $args['content']['description'] ) ) : ?>
				<div class="bb-section-description"><?php echo wp_kses_post( (string) $args['content']['description'] ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $bb_featured ) ) : ?>
		<div class="bb-services-featured bb-grid bb-grid-columns-1">
			<?php bb_render_service_items( $bb_featured ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $bb_rest ) ) : ?>
		<div class="bb-services-list bb-grid bb-grid-columns-<?php echo esc_attr( (string) $bb_columns ); ?>">
			<?php bb_render_service_items( $bb_rest ); ?>
		</div>
	<?php endif; ?>
</div>
