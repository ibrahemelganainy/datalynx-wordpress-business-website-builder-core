<?php
/**
 * Plugin Name: Business Builder Core
 * Description: Core engine for the Business Website Builder.
 * Version: 1.0.0
 * Author: Business Builder
 * Text Domain: business-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/

define( 'BB_CORE_VERSION', '1.0.0' );
define( 'BB_CORE_FILE', __FILE__ );
define( 'BB_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BB_CORE_URL', plugin_dir_url( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Core Bootstrap
|--------------------------------------------------------------------------
*/

/*
 * Load the Autoloader first.
 */
require_once BB_CORE_PATH . 'includes/Core/Autoloader.php';

/*
 * Register the Autoloader.
 */
BusinessBuilderCore\Core\Autoloader::register();

/*
 * Section Variant public API (procedural helpers). Loaded explicitly because
 * the autoloader only resolves classes. Provides bb_section_variants(),
 * bb_resolve_section_variant() and bb_render_section_variant() so packs can
 * register and render their own section layout variants.
 */
require_once BB_CORE_PATH . 'includes/Builder/section-variants.php';

/*
 * Section presentation state (Phase 22, procedural helpers). Loaded explicitly
 * because the autoloader only resolves classes. Resolves the glass / hover /
 * reveal state of a section from the TOKEN cascade, so the SectionRenderer can
 * emit `data-bb-glass` / `data-bb-hover` / `data-bb-reveal` on any registered
 * section - core or pack - without the renderer knowing a single section name.
 */
require_once BB_CORE_PATH . 'includes/Builder/section-presentation.php';

/*
 * Enterprise meta box assets (Phase 22 §27, §28).
 *
 * Registered ONCE here rather than by each pack, so a pack that declares a meta
 * box through `Admin\MetaBoxRenderer` automatically gets the card layout, the
 * tab semantics and the media picker without enqueueing anything itself. This is
 * the same "extend the existing architecture, do not duplicate it" rule the rest
 * of Phase 22 follows: one renderer, one stylesheet, one script.
 */
add_action(
    'init',
    array( 'BusinessBuilderCore\Admin\MetaBoxRenderer', 'register_assets' )
);

/*
 * Theme Shell Data bridge (Phase 16, procedural helpers). Loaded explicitly
 * because the autoloader only resolves classes. Bridges the plugin's business
 * settings (bb_site_settings) DOWN into the canonical Theme's prepared shell
 * data via the bb_theme_shell_*_data filters, keeping the Theme domain-agnostic.
 */
require_once BB_CORE_PATH . 'includes/Theme/theme-shell-data.php';

	/**
	 * Register the Business Builder packs that ship with this plugin.
	 *
	 * MEASURED FAILURE (Phase 22 audit, tests/_phase22-probe-bt.php)
	 * -----------------------------------------------------------
	 * `PackManager::register()` refuses a pack whose class does not yet exist:
	 *
	 *     if ( ! class_exists( $class ) ) { return false; }
	 *
	 * The autoloader only resolves `BusinessBuilderCore\Packs\*` on demand, and
	 * nothing has touched `LawFirmPack` at this point — so the CORE pack
	 * registration inside `ServiceProvider::register()` silently failed:
	 *
	 *     registered packs = medical      <-- LawFirm missing
	 *     boot(medical)    = true
	 *
	 * The consequence was not cosmetic: on a LawFirm site the LawFirm pack never
	 * booted, so its SECTIONS were never registered, so the Phase 22 Section
	 * Studio could not discover `lawyers`, `legal_services`, `practice_areas`,
	 * `testimonials` or `faq` — and neither could the Page Builder.
	 *
	 * The fix is the smallest one that works: name the class explicitly so it is
	 * loaded BEFORE the registry is asked about it, then hand the resolved
	 * classes to the EXISTING `bb_register_packs` action. No registry change, no
	 * new mechanism, and a pack added later still registers itself exactly as
	 * before (see the Medical pack below).
	 *
	 * Runs on priority 1 so every self-registering pack at the default priority
	 * is registered after this and can override a slug if it needs to.
	 */
	add_action(
		'bb_register_packs',
		function ( $pack_manager ) {

			$packs = array(
				'law_firm' => 'BusinessBuilderCore\Packs\LawFirm\LawFirmPack',
			);

			foreach ( $packs as $slug => $class ) {

				/* Load the class so the registry's `class_exists()` gate passes. */
				if ( ! class_exists( $class ) ) {
					continue;
				}

				$pack_manager->register( (string) $slug, (string) $class );
			}
		},
		1
	);

	/*
	 * Business Packs self-registration (Phase 18/19).
	 *
	 * A pack registers ITSELF through the `bb_register_packs` action the Core fires
	 * from ServiceProvider::register(), so the Core never hardcodes any pack and a
	 * new business type is drop-in. This declare-hook must run BEFORE the Core
	 * fires the action (i.e. before bb_core_run() below), which is exactly why it
	 * lives here in the bootstrap rather than inside a pack class.
	 *
	 * Core's own registry is never touched: ServiceProvider still registers
	 * `law_firm` and fires the filter, and each pack contributes its own mapping.
	 */
	add_action(
		'bb_register_packs',
		array(
			'BusinessBuilderCore\Packs\Medical\MedicalPack',
			'register_pack',
		)
	);

/*
|--------------------------------------------------------------------------
| Activation / Deactivation
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    array(
        'BusinessBuilderCore\Core\Activator',
        'activate',
    )
);

register_deactivation_hook(
    __FILE__,
    array(
        'BusinessBuilderCore\Core\Deactivator',
        'deactivate',
    )
);

/*
|--------------------------------------------------------------------------
| Run Plugin
|--------------------------------------------------------------------------
*/

function bb_core_run(): void {

    /*
     * Self-heal HTTPS transport for payment gateways: point cURL at an
     * existing CA bundle when php.ini has none, so a server without a
     * configured curl.cainfo can still reach PayPal/Stripe/Paymob.
     */
    BusinessBuilderCore\Core\Payments\HttpTransport::register();

    $plugin = new BusinessBuilderCore\Core\Plugin();

    $plugin->run();
}

bb_core_run();