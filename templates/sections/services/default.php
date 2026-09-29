<?php
/**
 * Services — default layout (cards in a grid).
 *
 * A variant is PRESENTATION only: the items arrive prepared in $args, and the
 * card markup itself is rendered by the shared helper so all five layouts stay
 * identical.
 *
 * @package BusinessBuilderCore
 *
 * @var array $args Prepared presentation data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$bb_columns = isset( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3;
$bb_style   = isset( $args['style'] ) ? sanitize_key( (string) $args['style'] ) : 'elevated';
?>
<div class="bb-services bb-services--default bb-services--<?php echo esc_attr( $bb_style ); ?>">
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

	<div class="bb-services-list bb-grid bb-grid-columns-<?php echo esc_attr( (string) $bb_columns ); ?>">
		<?php bb_render_service_items( $bb_items ); ?>
	</div>
</div>
