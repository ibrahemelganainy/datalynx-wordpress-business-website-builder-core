<?php
/**
 * Services — icon + text layout.
 *
 * The compact, text-forward presentation: a row of icon-led blocks. It is the
 * same content and the same card markup; only the class changes, so all the
 * Studio's shape / colour / spacing tokens still apply.
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
$bb_style   = isset( $args['style'] ) ? sanitize_key( (string) $args['style'] ) : 'flat';
?>
<div class="bb-services bb-services--icon-text bb-services--<?php echo esc_attr( $bb_style ); ?>">
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
