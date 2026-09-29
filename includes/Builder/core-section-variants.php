<?php
/**
 * Core section variants (Phase 23 §11, §17).
 *
 * The audit (docs/phase23-audit.md §5.1 G2) measured ZERO core variants: the
 * variant registry and the Studio's layout picker existed, but every core
 * section had exactly one layout, so the picker only ever appeared for pack
 * sections.
 *
 * This file registers the layouts the CORE sections can already express, using
 * the EXISTING `bb_register_section_variants` extension point. No new mechanism,
 * no new storage, no new token: a variant is a presentation strategy for the same
 * content, which is exactly what Phase 11 defined.
 *
 * Pack sections keep registering their own variants in their own pack, so the
 * generic layer still knows nothing about a business type.
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bb_register_core_section_variants' ) ) {

	/**
	 * Register the core variants.
	 *
	 * @param \BusinessBuilderCore\Builder\SectionVariants $variants Registry.
	 * @return void
	 */
	function bb_register_core_section_variants( $variants ): void {

		$templates = dirname( __DIR__, 2 ) . '/templates/sections';

		/*
		 * `services` (Phase 23 §11): five layouts over ONE content model. Each
		 * template is a thin wrapper — the item markup itself lives once, in
		 * `bb_render_service_items()`.
		 */
		$variants->register_many(
			'services',
			array(
				'default'    => $templates . '/services/default.php',
				'list'       => $templates . '/services/list.php',
				'featured'   => $templates . '/services/featured.php',
				'icon-text'  => $templates . '/services/icon-text.php',
				'image-text' => $templates . '/services/image-text.php',
			)
		);
	}

	add_action( 'bb_register_section_variants', 'bb_register_core_section_variants', 5 );
}
