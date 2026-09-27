<?php
/**
 * Theme Shell Data bridge.
 *
 * Bridges the plugin's business/site settings (option `bb_site_settings`,
 * owned by Settings\SiteSettings) DOWN into the canonical Theme's prepared
 * shell presentation data.
 *
 * DEPENDENCY DIRECTION (Phase 16):  pack -> plugin -> theme.
 * This file lives in the PLUGIN, so the Theme never learns about
 * `bb_site_settings` or any business concept. The Theme only ever sees the
 * GENERIC presentation fields it already documents (`contact` -> phone/email/
 * address, `social` -> labelled links, `cta`, `utility_links`). Replacing the
 * active business system (LawFirm, Medical, RealEstate, ...) requires NO change
 * to the Theme - only the business data source changes.
 *
 * The Theme exposes these filters (fired in inc/shell-data.php):
 *   - bb_theme_shell_header_data  ( array $data ) : array
 *   - bb_theme_shell_footer_data  ( array $data ) : array
 *
 * ESCAPING: this bridge only PREPARES data. It reuses SiteSettings' own
 * sanitizers (sanitize_text_field / sanitize_email / esc_url_raw) and leaves
 * output escaping to the Theme's helpers (esc_html/esc_url/esc_attr).
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bb_theme_shell_bridge_settings' ) ) {

	/**
	 * Read the business/site settings once per request (cached statically).
	 *
	 * Returns an empty array when the settings owner is unavailable, so the
	 * Theme simply shows nothing rather than erroring.
	 *
	 * @return array<string, mixed>
	 */
	function bb_theme_shell_bridge_settings(): array {

		/*
		 * No static cache: `get_option()` is already object-cached by WordPress
		 * for the request, so this is cheap, and it keeps the bridge correct even
		 * if settings change within a request (e.g. an admin save then re-render).
		 */
		if ( class_exists( '\BusinessBuilderCore\Settings\SiteSettings' ) ) {
			$owner = new \BusinessBuilderCore\Settings\SiteSettings();
			return $owner->get_all();
		}

		return array();
	}
}

if ( ! function_exists( 'bb_theme_shell_bridge_contact' ) ) {

	/**
	 * Map business settings into a GENERIC contact payload (or null when empty).
	 *
	 * Respects the SiteSettings `show_phone` / `show_email` / `show_address`
	 * display toggles so the owner's visibility choices are honoured.
	 *
	 * @param bool $for_header Whether the payload targets the header slot.
	 * @return array<string, string>|null
	 */
	function bb_theme_shell_bridge_contact( bool $for_header = false ): ?array {

		$s = bb_theme_shell_bridge_settings();

		if ( empty( $s ) ) {
			return null;
		}

		$contact = array();

		if ( ! empty( $s['show_phone'] ) && ! empty( $s['phone'] ) ) {
			$contact['phone'] = (string) $s['phone'];
		}

		if ( ! empty( $s['show_email'] ) && ! empty( $s['email'] ) ) {
			$contact['email'] = (string) $s['email'];
		}

		/* The header slot intentionally never carries the address (too long). */
		if ( ! $for_header && ! empty( $s['show_address'] ) && ! empty( $s['address'] ) ) {
			$contact['address'] = (string) $s['address'];
		}

		return empty( $contact ) ? null : $contact;
	}
}

if ( ! function_exists( 'bb_theme_shell_bridge_social' ) ) {

	/**
	 * Map business social settings into a GENERIC labelled social list.
	 *
	 * Each network is optional; missing/blank values are simply omitted (the
	 * Theme renders nothing for an empty list).
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	function bb_theme_shell_bridge_social(): array {

		$s = bb_theme_shell_bridge_settings();

		if ( empty( $s ) ) {
			return array();
		}

		$networks = array(
			'facebook'  => __( 'Facebook', 'business-builder' ),
			'instagram' => __( 'Instagram', 'business-builder' ),
			'youtube'   => __( 'YouTube', 'business-builder' ),
			'linkedin'  => __( 'LinkedIn', 'business-builder' ),
			'twitter'   => __( 'Twitter', 'business-builder' ),
		);

		$links = array();

		foreach ( $networks as $key => $label ) {

			if ( empty( $s[ $key ] ) ) {
				continue;
			}

			$url = esc_url_raw( (string) $s[ $key ] );

			if ( '' === $url ) {
				continue;
			}

			$links[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $links;
	}
}

if ( ! function_exists( 'bb_theme_shell_bridge_header_data' ) ) {

	/**
	 * Filter: bb_theme_shell_header_data.
	 *
	 * Adds a generic header contact slot (phone/email) when the business system
	 * supplies and enables it. A CTA is NOT added: no authoritative CTA source
	 * exists in the plugin, so inventing one is out of scope (Phase 16 audit).
	 *
	 * @param array<string, mixed> $data Prepared header data from the Theme.
	 * @return array<string, mixed>
	 */
	function bb_theme_shell_bridge_header_data( $data ): array {

		if ( ! is_array( $data ) ) {
			return (array) $data;
		}

		if ( empty( $data['contact'] ) ) {
			$data['contact'] = bb_theme_shell_bridge_contact( true );
		}

		return $data;
	}
}

if ( ! function_exists( 'bb_theme_shell_bridge_footer_data' ) ) {

	/**
	 * Filter: bb_theme_shell_footer_data.
	 *
	 * Adds generic footer contact + social slots when the business system
	 * supplies them. Never overwrites a value another owner already provided
	 * (the filter is additive and non-destructive).
	 *
	 * @param array<string, mixed> $data Prepared footer data from the Theme.
	 * @return array<string, mixed>
	 */
	function bb_theme_shell_bridge_footer_data( $data ): array {

		if ( ! is_array( $data ) ) {
			return (array) $data;
		}

		if ( empty( $data['contact'] ) ) {
			$data['contact'] = bb_theme_shell_bridge_contact( false );
		}

		if ( empty( $data['social'] ) ) {
			$data['social'] = bb_theme_shell_bridge_social();
		}

		return $data;
	}
}

if ( ! function_exists( 'bb_theme_shell_bridge_register' ) ) {

	/**
	 * Register the Theme shell data bridge.
	 *
	 * The bridge only subscribes to filters the canonical Theme fires. When the
	 * active theme is not the Business Builder Theme those filters never fire,
	 * so this is harmless on any other theme (e.g. Astra).
	 */
	function bb_theme_shell_bridge_register(): void {

		add_filter(
			'bb_theme_shell_header_data',
			'bb_theme_shell_bridge_header_data',
			10,
			1
		);

		add_filter(
			'bb_theme_shell_footer_data',
			'bb_theme_shell_bridge_footer_data',
			10,
			1
		);
	}
}

/*
 * Filters must be registered before the Theme renders the shell (which happens
 * during template loading on `template_redirect`/render). Hooking on `after_setup_theme`
 * is early enough for every front-end shell render and avoids any admin cost.
 */
add_action( 'after_setup_theme', 'bb_theme_shell_bridge_register' );