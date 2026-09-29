<?php
/**
 * Shared service-item renderer (Phase 23 §7, §11, §26).
 *
 * ONE definition of what a service item looks like, used by BOTH the core inline
 * renderer and every registered `services` variant template. Duplicating the
 * markup per layout would mean five places to fix whenever the card changes.
 *
 * It is presentation only: items arrive already prepared (the caller owns the
 * query), so a variant template never touches the database.
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bb_prepare_service_item' ) ) {

	/**
	 * Normalize one service item for rendering.
	 *
	 * @param mixed $item Raw item.
	 * @return array<string, mixed>|null Null when the item is not usable.
	 */
	function bb_prepare_service_item( $item ): ?array {

		if ( ! is_array( $item ) ) {
			return null;
		}

		return array(
			'title'       => isset( $item['title'] ) ? (string) $item['title'] : '',
			'description' => isset( $item['description'] ) ? (string) $item['description'] : '',
			'link_url'    => isset( $item['link_url'] ) ? (string) $item['link_url'] : '',
			'link_text'   => isset( $item['link_text'] ) ? (string) $item['link_text'] : '',
			'image'       => isset( $item['image'] ) ? absint( $item['image'] ) : 0,
			'icon'        => isset( $item['icon'] ) ? (string) $item['icon'] : '',
		);
	}
}

if ( ! function_exists( 'bb_render_service_items' ) ) {

	/**
	 * Render the items of a services section.
	 *
	 * @param array[] $items Prepared items.
	 * @return void
	 */
	function bb_render_service_items( array $items ): void {

		foreach ( $items as $raw ) {

			$item = bb_prepare_service_item( $raw );

			if ( null === $item ) {
				continue;
			}

			/*
			 * The icon goes through the ONE icon renderer: it validates the slug,
			 * returns '' for an unknown value, and marks the request as needing the
			 * icon font — which is what makes the asset load conditional.
			 */
			$icon_html = function_exists( 'bb_render_icon' )
				? bb_render_icon( $item['icon'], array( 'label' => $item['title'] ) )
				: '';

			?>
			<article class="bb-service-card">
				<?php if ( $item['image'] > 0 ) : ?>
					<div class="bb-service-card-media">
						<?php
						echo wp_get_attachment_image(
							$item['image'],
							'medium_large',
							false,
							array( 'loading' => 'lazy' )
						);
						?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $icon_html ) : ?>
					<div class="bb-service-card-icon">
						<?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- produced by the validated icon renderer. ?>
					</div>
				<?php endif; ?>

				<div class="bb-service-card-body">
					<?php if ( '' !== $item['title'] ) : ?>
						<h3 class="bb-service-card-title"><?php echo esc_html( $item['title'] ); ?></h3>
					<?php endif; ?>

					<?php if ( '' !== $item['description'] ) : ?>
						<div class="bb-service-card-text"><?php echo wp_kses_post( $item['description'] ); ?></div>
					<?php endif; ?>

					<?php if ( '' !== $item['link_url'] ) : ?>
						<a class="bb-service-card-link" href="<?php echo esc_url( $item['link_url'] ); ?>">
							<?php echo esc_html( '' !== $item['link_text'] ? $item['link_text'] : __( 'Learn more', 'business-builder' ) ); ?>
						</a>
					<?php endif; ?>
				</div>
			</article>
			<?php
		}
	}
}
