<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only Design preview (Phase 21 §11, §32).
 *
 * Lets a site admin see a Design on THEIR OWN front end BEFORE applying it. The critical
 * property is that it is genuinely read-only:
 *
 *   - nothing is written: no theme mod, no option, no post, no pack data;
 *   - the previewed design is injected IN MEMORY, only for this one request;
 *   - it requires a nonce and the customization capability;
 *   - it can only preview a design that is valid for THIS site's business type.
 *
 * It reuses the Theme's own rendering: the front end is rendered normally, so the preview
 * cannot drift from the real Theme (there is no separate preview renderer, no screenshot).
 */
class DesignPreview {

	/**
	 * Query var carrying the design slug.
	 */
	public const QUERY_VAR = 'bb_design_preview';

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'bb_design_preview';

	protected DesignCatalogue $catalogue;

	public function __construct( ?DesignCatalogue $catalogue = null ) {

		$this->catalogue = $catalogue ?: new DesignCatalogue();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {

		add_filter( 'bb_theme_active_preset', array( $this, 'override_active_preset' ) );

		/*
		 * Tell the admin that this page is a preview, so nobody mistakes it for the live site.
		 * Rendered only when a preview is actually active.
		 */
		add_action( 'wp_footer', array( $this, 'render_preview_banner' ) );
	}

	/**
	 * The design requested for preview in this request, or '' when none.
	 *
	 * @return string
	 */
	public function requested(): string {

		if ( is_admin() ) {
			return '';
		}

		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return '';
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return '';
		}

		if ( $this->catalogue->current_user_can_customize( get_current_blog_id() ) === false ) {
			return '';
		}

		$slug = sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) );

		if ( '' === $slug ) {
			return '';
		}

		/* Previewing is still subject to registry + business-type validation. */
		$business_type = $this->catalogue->business_type_of( new \BusinessBuilderCore\Settings\BusinessType() );

		if ( ! $this->catalogue->is_valid_for( $slug, $business_type ) ) {
			return '';
		}

		return $slug;
	}

	/**
	 * Swap the active preset for the duration of this request ONLY.
	 *
	 * The Theme's resolver runs this filter after reading the theme mod and before validating,
	 * so the returned slug is still checked against the registry — an invalid value cannot get
	 * through even if this returned one.
	 *
	 * @param string $preset Resolved active preset.
	 * @return string
	 */
	public function override_active_preset( $preset ) {

		$requested = $this->requested();

		return '' !== $requested ? $requested : $preset;
	}

	/**
	 * A small, unobtrusive banner so a preview is never mistaken for the live site.
	 */
	public function render_preview_banner(): void {

		$requested = $this->requested();

		if ( '' === $requested ) {
			return;
		}

		$design = $this->catalogue->all()[ $requested ] ?? null;

		$label = $design ? (string) $design['label'] : $requested;

		printf(
			'<div style="position:fixed;inset-block-end:0;inset-inline:0;z-index:99999;background:#1d2327;color:#fff;padding:10px 16px;font:13px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;text-align:center;">%s</div>',
			esc_html(
				sprintf(
					/* translators: %s: design name. */
					__( 'Design preview: %s — this is not your live site and nothing has been saved.', 'business-builder' ),
					$label
				)
			)
		);
	}
}