<?php

namespace BusinessBuilderCore\Design;

use BusinessBuilderCore\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Design screen (Phase 21 §10-§19, §48-§49) — `Business Builder → Design`.
 *
 * The customer-facing surface for the Design Catalogue, presented as a premium template
 * catalogue rather than a WordPress list table:
 *
 *   - a large CURRENT DESIGN panel with a real preview and a Customize action;
 *   - a responsive grid of design cards, each showing a REAL preview of that design, its
 *     palette strip, a type specimen, its shell summary and its active state;
 *   - a modal preview with Desktop / Tablet / Mobile modes and LTR / RTL toggle, rendered
 *     from the design's own tokens (§19);
 *   - the three DISTINCT reset operations of §16.
 *
 * It remains a deliberately thin UI over the EXISTING Theme design system: it SELECTS a design
 * (writing only `bb_theme_preset`), LINKS to the existing Customizer for deep editing (no second
 * customization system, §15), and never touches business type, pack, domain or content.
 *
 * The screen is plain WordPress admin markup with one stylesheet and one small script — no SPA,
 * no build step (§10). Layout uses logical CSS properties and a class-based device frame, so it
 * is responsive and RTL/LTR safe by construction.
 */
class DesignPage {

	/**
	 * Screen slug.
	 */
	public const SLUG = 'business-builder-design';

	/**
	 * Admin-post actions.
	 */
	public const ACTION_APPLY           = 'bb_design_apply';
	public const ACTION_RESET_ALL       = 'bb_design_reset_all';
	public const ACTION_RESTORE_DEFAULT = 'bb_design_restore_default';
	public const ACTION_RESET_CONTROL   = 'bb_design_reset_control';

	/**
	 * Saves the Design Studio's customization form (§20, §43).
	 */
	public const ACTION_SAVE            = 'bb_design_save';

	/**
	 * Saves the Section Studio's per-section visual overrides (Phase 22 §14-§19, §25).
	 */
	public const ACTION_SAVE_SECTIONS   = 'bb_design_save_sections';

	/**
	 * Resets ONE section's visual overrides to the design/global state (Phase 22 §30).
	 */
	public const ACTION_RESET_SECTION   = 'bb_design_reset_section';

	/**
	 * Resets EVERY section's visual overrides for this site (Phase 22 §30).
	 */
	public const ACTION_RESET_SECTIONS  = 'bb_design_reset_sections';

	/**
	 * Saves the Header / Footer / Background / Grid / Motion / Glass groups.
	 *
	 * Deliberately the SAME action as ACTION_SAVE: these are ordinary global design controls, so
	 * they persist through the same `bb_design_<key>` theme mods and the same validator. A second
	 * handler would be a second code path to the same storage for no benefit (§29).
	 */
	public const ACTION_SAVE_SHELL      = 'bb_design_save';

	/**
	 * The capability required to customize a site's design.
	 *
	 * The same capability the Theme's Customizer uses, so the two surfaces cannot diverge.
	 */
	public const CAPABILITY = 'edit_theme_options';

	protected Plugin $plugin;

	protected DesignCatalogue $catalogue;

	protected DesignPreviewRenderer $renderer;

	   protected DesignStudioUI $studio;

	/**
	 * The per-section Studio (Phase 22).
	 */
	protected SectionStudioUI $section_studio;

	/**
	 * The section-level design layer (Phase 22).
	 */
	protected SectionStyleSchema $section_schema;

	public function __construct( Plugin $plugin ) {

		$this->plugin    = $plugin;
		$this->catalogue = new DesignCatalogue();
		$this->renderer  = new DesignPreviewRenderer();
		$this->studio    = new DesignStudioUI( null, null, null, null, $this->catalogue );

		/*
		 * The section layer reads the SAME SectionRegistry the page builder uses, so a section a
		 * pack registers is discoverable by the Studio with no extra wiring.
		 */
		$this->section_schema = new SectionStyleSchema( $plugin->get_section_registry() );
		$this->section_studio = new SectionStudioUI( $this->section_schema );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {

		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		add_action( 'admin_post_' . self::ACTION_APPLY, array( $this, 'handle_apply' ) );
		add_action( 'admin_post_' . self::ACTION_RESET_ALL, array( $this, 'handle_reset_all' ) );
		add_action( 'admin_post_' . self::ACTION_RESTORE_DEFAULT, array( $this, 'handle_restore_default' ) );
		add_action( 'admin_post_' . self::ACTION_RESET_CONTROL, array( $this, 'handle_reset_control' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );

		/* Phase 22: section-level design writes. */
		add_action( 'admin_post_' . self::ACTION_SAVE_SECTIONS, array( $this, 'handle_save_sections' ) );
		add_action( 'admin_post_' . self::ACTION_RESET_SECTION, array( $this, 'handle_reset_section' ) );
		add_action( 'admin_post_' . self::ACTION_RESET_SECTIONS, array( $this, 'handle_reset_sections' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the screen under the existing Business Builder site menu.
	 *
	 * Placed directly after "Site Settings" so the site admin's mental model is
	 * Settings → Design → Custom Domain, all inside the menu that already exists.
	 */
	public function register_menu(): void {

		add_submenu_page(
			'business-builder',
			esc_html__( 'Design', 'business-builder' ),
			esc_html__( 'Design', 'business-builder' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * The screen's stylesheet and the small preview-modal script.
	 *
	 * Uses the plugin's admin asset directory so the Design screen's presentation lives with the
	 * screen. Only the design assets are loaded, and only on this screen.
	 */
	public function enqueue( string $hook = '' ): void {

		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		$css_path = BB_CORE_PATH . 'assets/css/admin/design-preview.css';

		wp_enqueue_style(
			'bb-design-admin',
			BB_CORE_URL . 'assets/css/admin/design-preview.css',
			array(),
			is_readable( $css_path ) ? (string) filemtime( $css_path ) : BB_CORE_VERSION
		);

		/* The Studio panel's own stylesheet (palettes, swatches, specimens, preview frame). */
		$panel_css = BB_CORE_PATH . 'assets/css/admin/design-studio-panel.css';

		wp_enqueue_style(
			'bb-design-studio',
			BB_CORE_URL . 'assets/css/admin/design-studio-panel.css',
			array( 'bb-design-admin' ),
			is_readable( $panel_css ) ? (string) filemtime( $panel_css ) : BB_CORE_VERSION
		);

		$js_path = BB_CORE_PATH . 'assets/js/admin/design-studio.js';

		wp_enqueue_script(
			'bb-design-admin',
			BB_CORE_URL . 'assets/js/admin/design-studio.js',
			array(),
			is_readable( $js_path ) ? (string) filemtime( $js_path ) : BB_CORE_VERSION,
			true
		);

		/* The Studio panel's live-preview behaviour (§43). */
		$panel_js = BB_CORE_PATH . 'assets/js/admin/design-studio-panel.js';

		wp_enqueue_script(
			'bb-design-studio',
			BB_CORE_URL . 'assets/js/admin/design-studio-panel.js',
			array( 'bb-design-admin' ),
			is_readable( $panel_js ) ? (string) filemtime( $panel_js ) : BB_CORE_VERSION,
			true
		);

		/*
		 * Phase 22 section panels. Depends on the global Studio script so the two share
		 * one preview-updating path and cannot race for the preview element.
		 */
		$section_js = BB_CORE_PATH . 'assets/js/admin/section-studio-panel.js';

		wp_enqueue_script(
			'bb-section-studio',
			BB_CORE_URL . 'assets/js/admin/section-studio-panel.js',
			array( 'bb-design-studio' ),
			is_readable( $section_js ) ? (string) filemtime( $section_js ) : BB_CORE_VERSION,
			true
		);

		/*
		 * The endpoints the section reset uses. Passed from the server rather than built in
		 * JavaScript, so the admin URL and the action names have exactly one definition.
		 */
		wp_localize_script(
			'bb-section-studio',
			'BBSectionStudio',
			array(
				'action'      => admin_url( 'admin-post.php' ),
				'resetAction' => self::ACTION_RESET_SECTION,
				'returnUrl'   => $this->return_url(),
			)
		);
	}

	/**
	 * Guard: the current user must be able to customize THIS site's design.
	 */
	protected function authorize(): void {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have permission to change the design of this site.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {

		$this->authorize();

		/* Site-local by construction: the screen always acts on the CURRENT site. */
		$blog_id = get_current_blog_id();

		$business_type = $this->catalogue->business_type_of( $this->plugin->get_business_type(), $blog_id );

		$active_slug = $this->catalogue->active( $blog_id );

		$designs = $this->catalogue->for_business_type( $business_type );

		$active = isset( $designs[ $active_slug ] ) ? $designs[ $active_slug ] : null;

		$override_count = 0;

		if ( function_exists( 'bb_theme_customization_overrides' ) ) {
			$override_count = count( bb_theme_customization_overrides() );
		}

		$return = $this->return_url();

		/*
		 * A neutral sample name for the previews: the site's own name, so the preview reads as
		 * THIS customer's site rather than a generic mockup. Escaped at render time.
		 */
		$sample = (string) get_bloginfo( 'name' );

		?>
		<div class="wrap bb-design-studio">

			<div class="bb-design-studio-head">
				<h1><?php esc_html_e( 'Design Studio', 'business-builder' ); ?></h1>

				<p class="bb-design-studio-lede">
					<?php esc_html_e( 'Choose the visual identity for your website. Each design is a complete, professionally crafted look — colours, typography, spacing, gradients, cards and buttons all change together.', 'business-builder' ); ?>
				</p>
			</div>

			<?php $this->render_notice(); ?>

			<?php if ( $active ) : ?>

				<section class="bb-design-current" aria-labelledby="bb-design-current-title">

					<div class="bb-design-current-preview">
						<?php
						echo $this->renderer->render( $active, 'desktop', 'modal', false, $sample ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
						?>
					</div>

					<div class="bb-design-current-body">

						<span class="bb-design-current-eyebrow"><?php esc_html_e( 'Current design', 'business-builder' ); ?></span>

						<h2 id="bb-design-current-title" class="bb-design-current-title">
							<?php echo esc_html( (string) $active['label'] ); ?>
						<span class="bb-design-badge bb-design-badge--active">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Active', 'business-builder' ); ?>
						</span>
						</h2>

						<p class="bb-design-current-desc"><?php echo esc_html( (string) $active['description'] ); ?></p>

						<div class="bb-design-current-meta">
							<?php echo $this->renderer->palette_strip( $active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
							<?php echo $this->renderer->type_specimen( $active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
						</div>

						<?php $shell_summary = $this->renderer->shell_summary( $active ); ?>

						<?php if ( '' !== $shell_summary ) : ?>
							<p class="bb-design-current-shell"><?php echo esc_html( $shell_summary ); ?></p>
						<?php endif; ?>

						<p class="bb-design-current-custom">
							<?php
							printf(
								/* translators: %d: number of customizations applied. */
								esc_html( _n( '%d customization applied.', '%d customizations applied.', $override_count, 'business-builder' ) ),
								(int) $override_count
							);
							?>
						</p>

						<div class="bb-design-current-actions">
							<a class="button button-primary button-hero" href="#bb-studio">
								<?php esc_html_e( 'Customize this design', 'business-builder' ); ?>
							</a>
						</div>

					</div>
				</section>

			<?php endif; ?>

			<h2 class="bb-design-studio-section-title"><?php esc_html_e( 'Available designs', 'business-builder' ); ?></h2>

			<?php if ( count( $designs ) <= 1 ) : ?>

				<div class="bb-design-empty">
					<p><?php esc_html_e( 'No additional designs are available for this business type yet.', 'business-builder' ); ?></p>
				</div>

			<?php else : ?>

				<div class="bb-design-grid">
					<?php foreach ( $designs as $slug => $design ) : ?>

						<?php $is_active = (string) $slug === $active_slug; ?>

						<div class="bb-design-card<?php echo $is_active ? ' is-active' : ''; ?>" data-bb-design-card="<?php echo esc_attr( (string) $slug ); ?>">

							<div class="bb-design-card-preview">
								<?php echo $this->renderer->render( $design, 'desktop', 'card', false, $sample ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>

								<?php if ( $is_active ) : ?>
									<span class="bb-design-card-flag">
										<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
										<?php esc_html_e( 'Active', 'business-builder' ); ?>
									</span>
								<?php endif; ?>
							</div>

							<div class="bb-design-card-body">

								<h3 class="bb-design-card-title"><?php echo esc_html( (string) $design['label'] ); ?></h3>

								<p class="bb-design-card-desc"><?php echo esc_html( (string) $design['description'] ); ?></p>

								<div class="bb-design-card-meta">
									<?php echo $this->renderer->palette_strip( $design ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
									<?php echo $this->renderer->type_specimen( $design ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
								</div>

							</div>

							<div class="bb-design-card-actions">

								<button
									type="button"
									class="button bb-design-preview-trigger"
									data-bb-preview-design="<?php echo esc_attr( (string) $slug ); ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s: design name. */ __( 'Preview the %s design', 'business-builder' ), (string) $design['label'] ) ); ?>"
								>
									<?php esc_html_e( 'Preview', 'business-builder' ); ?>
								</button>

								<?php if ( $is_active ) : ?>

									<span class="bb-design-card-current"><?php esc_html_e( 'Currently active', 'business-builder' ); ?></span>

								<?php else : ?>

									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-design-card-form">
										<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_APPLY ); ?>">
										<input type="hidden" name="design" value="<?php echo esc_attr( (string) $slug ); ?>">
										<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return ); ?>">
										<?php wp_nonce_field( self::ACTION_APPLY ); ?>

										<button type="submit" class="button button-primary">
											<?php esc_html_e( 'Use this design', 'business-builder' ); ?>
										</button>
									</form>

								<?php endif; ?>

							</div>

						</div>

					<?php endforeach; ?>
				</div>

			<?php endif; ?>

			<?php /*
			 * The "Keep my customizations" variant is offered ONCE, below the grid, rather than as a
			 * second button on every card. §13 requires the choice to be explicit; repeating it 5×
			 * would clutter the catalogue and obscure the primary action (§37).
			 */
			if ( count( $designs ) > 1 ) : ?>

				<details class="bb-design-advanced">
					<summary><?php esc_html_e( 'Advanced: switch design and keep my customizations', 'business-builder' ); ?></summary>

					<p class="description">
						<?php esc_html_e( 'By default, applying a design restores that design\'s original values, so it appears exactly as its author intended. Use this option only if you want your current customizations carried across to a different design.', 'business-builder' ); ?>
					</p>

					<?php foreach ( $designs as $slug => $design ) : ?>

						<?php if ( (string) $slug === $active_slug ) : continue; endif; ?>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-design-advanced-row">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_APPLY ); ?>">
							<input type="hidden" name="design" value="<?php echo esc_attr( (string) $slug ); ?>">
							<input type="hidden" name="keep_customizations" value="1">
							<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return ); ?>">
							<?php wp_nonce_field( self::ACTION_APPLY ); ?>

							<span class="bb-design-advanced-label"><?php echo esc_html( (string) $design['label'] ); ?></span>

							<button type="submit" class="button"><?php esc_html_e( 'Switch and keep customizations', 'business-builder' ); ?></button>
						</form>

					<?php endforeach; ?>
				</details>

			<?php endif; ?>

			<?php
			/*
			 * The Design Studio (§20-§29). It renders its own reset section (§45) with clearer wording
			 * than a separate block would, so the page has ONE place to customize and reset.
			 */
			$this->studio->render( $return, self::ACTION_SAVE );

			/*
			 * The Section Studio (Phase 22 §14-§19). Placed directly after the global Studio so the
			 * customer's mental model reads top-down: choose the identity, tune it, then adjust
			 * individual sections. It discovers its own section list from the registry, so it needs no
			 * configuration here.
			 */
			$this->section_studio->render( $return, self::ACTION_SAVE_SECTIONS );
			?>

		</div>

		<?php $this->render_modals( $designs, $sample ); ?>
		<?php
	}

	/**
	 * The preview modals (§19).
	 *
	 * One modal per design, written once into the page and shown on demand by the small script.
	 * Each modal contains THREE device previews (desktop / tablet / mobile) plus a direction
	 * toggle, all rendered from the design's own tokens — so the modal preview and the live site
	 * cannot disagree (§58).
	 *
	 * @param array<string, array<string, mixed>> $designs Normalized designs.
	 * @param string                              $sample  Sample site name for the preview copy.
	 */
	protected function render_modals( array $designs, string $sample ): void {

		foreach ( $designs as $slug => $design ) :

			$label = isset( $design['label'] ) ? (string) $design['label'] : (string) $slug;

			?>
			<div
				class="bb-design-modal"
				data-bb-design-modal="<?php echo esc_attr( (string) $slug ); ?>"
				hidden
				role="dialog"
				aria-modal="true"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: design name. */ __( '%s design preview', 'business-builder' ), $label ) ); ?>"
			>
				<div class="bb-design-modal-backdrop" data-bb-modal-close></div>

				<div class="bb-design-modal-panel" role="document">

					<div class="bb-design-modal-head">
						<div>
							<h2 class="bb-design-modal-title"><?php echo esc_html( $label ); ?></h2>
							<p class="bb-design-modal-desc"><?php echo esc_html( (string) ( $design['description'] ?? '' ) ); ?></p>
						</div>

						<button type="button" class="bb-design-modal-close" data-bb-modal-close>
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Close preview', 'business-builder' ); ?></span>
						</button>
					</div>

					<div class="bb-design-modal-toolbar">

						<div class="bb-design-modal-devices" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'business-builder' ); ?>">
							<button type="button" class="bb-design-modal-device is-active" data-bb-device="desktop">
								<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Desktop', 'business-builder' ); ?></span>
							</button>
							<button type="button" class="bb-design-modal-device" data-bb-device="tablet">
								<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Tablet', 'business-builder' ); ?></span>
							</button>
							<button type="button" class="bb-design-modal-device" data-bb-device="mobile">
								<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Mobile', 'business-builder' ); ?></span>
							</button>
						</div>

						<button type="button" class="button bb-design-modal-rtl" data-bb-direction aria-pressed="false">
							<?php esc_html_e( 'RTL', 'business-builder' ); ?>
						</button>
					</div>

					<div class="bb-design-modal-stage">

						<?php foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) : ?>
							<div
								class="bb-design-modal-frame bb-design-modal-frame--<?php echo esc_attr( $device ); ?><?php echo 'desktop' === $device ? ' is-active' : ''; ?>"
								data-bb-device-frame="<?php echo esc_attr( $device ); ?>"
							>
								<?php echo $this->renderer->render( $design, $device, 'modal', false, $sample ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
								<?php echo $this->renderer->render( $design, $device, 'modal', true, $sample ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
							</div>
						<?php endforeach; ?>

					</div>

					<div class="bb-design-modal-foot">

						<?php echo $this->renderer->palette_strip( $design ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>

						<?php $shell_summary = $this->renderer->shell_summary( $design ); ?>

						<?php if ( '' !== $shell_summary ) : ?>
							<span class="bb-design-modal-shell"><?php echo esc_html( $shell_summary ); ?></span>
						<?php endif; ?>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-design-modal-apply">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_APPLY ); ?>">
							<input type="hidden" name="design" value="<?php echo esc_attr( (string) $slug ); ?>">
							<input type="hidden" name="bb_return" value="<?php echo esc_attr( $this->return_url() ); ?>">
							<?php wp_nonce_field( self::ACTION_APPLY ); ?>

							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Use this design', 'business-builder' ); ?>
							</button>
						</form>

					</div>

				</div>
			</div>

			<?php endforeach;
		}

		/**
		 * Render the result notice from the last action.
		 */
		protected function render_notice(): void {

		if ( ! isset( $_GET['bb_design_result'] ) ) {
			return;
		}

		$code = sanitize_key( (string) wp_unslash( $_GET['bb_design_result'] ) );

		$messages = array(
			'applied'   => array( 'success', __( 'The design was applied.', 'business-builder' ) ),
			'kept'      => array( 'success', __( 'The design was applied and your customizations were kept.', 'business-builder' ) ),
			'reset'     => array( 'success', __( 'Your customizations were reset to the design\'s original values.', 'business-builder' ) ),
			'default'   => array( 'success', __( 'The Default design was restored.', 'business-builder' ) ),
			'saved'     => array( 'success', __( 'Your changes were saved.', 'business-builder' ) ),
			'section_reset'  => array( 'success', __( 'That section was returned to the design and site settings.', 'business-builder' ) ),
			'sections_reset' => array( 'success', __( 'All section settings were reset.', 'business-builder' ) ),
			'forbidden' => array( 'error', __( 'You are not allowed to change this site\'s design.', 'business-builder' ) ),
			'invalid'   => array( 'error', __( 'That design is not available for this site.', 'business-builder' ) ),
			'failed'    => array( 'error', __( 'The design could not be changed.', 'business-builder' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		/*
		 * The Studio reports how many controls it actually stored, so "saved" is never a vague
		 * reassurance: if every value was rejected by the validator, the message reflects that.
		 */
		$message = $messages[ $code ][1];

		if ( 'saved' === $code ) {

			$saved    = isset( $_GET['bb_saved'] ) ? absint( wp_unslash( $_GET['bb_saved'] ) ) : 0;
			$cleared  = isset( $_GET['bb_cleared'] ) ? absint( wp_unslash( $_GET['bb_cleared'] ) ) : 0;
			$sections = isset( $_GET['bb_sections'] ) ? absint( wp_unslash( $_GET['bb_sections'] ) ) : 0;

			$extra = '';

			if ( $cleared > 0 ) {
				$extra = sprintf(
					/* translators: %d: number of settings restored to the design's own value. */
					_n( ', %d restored to the design\'s value', ', %d restored to the design\'s value', $cleared, 'business-builder' ),
					(int) $cleared
				);
			}

			/*
			 * The Section Studio reports how many SECTION types it actually stored, so "saved" is
			 * never a vague reassurance for a section-level edit either (§25: a setting that saves
			 * but produces no change is not a completed feature - and a setting that was REJECTED
			 * must not be reported as saved).
			 */
			if ( $sections > 0 ) {
				$extra .= sprintf(
					/* translators: %d: number of sections customized. */
					_n( ', across %d section', ', across %d sections', $sections, 'business-builder' ),
					(int) $sections
				);
			}

			$message = sprintf(
				/* translators: 1: number of settings saved, 2: optional note about restored/customized settings. */
				_n( '%1$d setting saved%2$s.', '%1$d settings saved%2$s.', $saved, 'business-builder' ),
				(int) $saved,
				$extra
			);
		}

		if ( 'sections_reset' === $code ) {

			$removed = isset( $_GET['bb_removed'] ) ? absint( wp_unslash( $_GET['bb_removed'] ) ) : 0;

			$message = sprintf(
				/* translators: %d: number of sections reset. */
				_n( '%d section was returned to the design and site settings.', '%d sections were returned to the design and site settings.', $removed, 'business-builder' ),
				(int) $removed
			);
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $message )
		);
	}

	/**
	 * The URL of this screen (used as a validated local return target).
	 *
	 * @return string
	 */
	protected function return_url(): string {

		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * The existing Customizer URL, pre-focused on the design controls (§15).
	 *
	 * Phase 21 deliberately does NOT reimplement customization: it routes the customer to the
	 * one authoritative customization system.
	 *
	 * @return string
	 */
	protected function customizer_url(): string {

		return add_query_arg(
			array( 'autofocus[section]' => 'bb_design' ),
			admin_url( 'customize.php' )
		);
	}

	/**
	 * The read-only preview URL for a design (§11).
	 *
	 * Previews render the FRONT END with an in-memory preset override; nothing is saved. Kept so a
	 * customer can open the design on their own real site, in a new tab, alongside the in-page
	 * modal preview.
	 *
	 * @param string $slug Design slug.
	 * @return string
	 */
	protected function preview_url( string $slug ): string {

		return add_query_arg(
			array(
				'bb_design_preview' => sanitize_key( $slug ),
				'_wpnonce'          => wp_create_nonce( DesignPreview::NONCE_ACTION ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Redirect back to the screen with a result code.
	 *
	 * @param string $code Result code.
	 * @param array  $extra Extra query args.
	 */
	protected function redirect( string $code, array $extra = array() ): void {

		$target = $this->return_url();

		/* Only ever redirect within this admin screen. */
		if ( isset( $_POST['bb_return'] ) ) {
			$candidate = esc_url_raw( wp_unslash( (string) $_POST['bb_return'] ) );
			$expected  = $this->return_url();

			if ( $candidate === $expected ) {
				$target = $candidate;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array_merge( array( 'bb_design_result' => sanitize_key( $code ) ), $extra ),
				$target
			)
		);

		exit;
	}

	/**
	 * Apply a design (§12).
	 *
	 * SECURITY (§26): capability → nonce → CURRENT SITE (never a posted blog id) → registry and
	 * business-type validation → mutate. Only the existing `bb_theme_preset` mod is written.
	 */
	public function handle_apply(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_APPLY );

		/*
		 * The target site is ALWAYS the current site. No `blog_id` is read from the request, so
		 * a forged id cannot redirect the write to another site (§26, §27).
		 */
		$blog_id = get_current_blog_id();

		$slug = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';

		$keep = ! empty( $_POST['keep_customizations'] );

		$result = $this->catalogue->apply(
			$this->plugin->get_business_type(),
			$slug,
			! $keep,
			$blog_id
		);

		if ( ! $result['ok'] ) {
			$this->redirect( $result['error'] );
		}

		$this->redirect( $keep ? 'kept' : 'applied' );
	}

	/**
	 * Reset ALL customizations (§16).
	 */
	public function handle_reset_all(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_RESET_ALL );

		$result = $this->catalogue->reset_customization( get_current_blog_id() );

		$this->redirect( $result['ok'] ? 'reset' : 'forbidden' );
	}

	/**
	 * Restore the Default design (§16).
	 */
	public function handle_restore_default(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_RESTORE_DEFAULT );

		$result = $this->catalogue->restore_default( $this->plugin->get_business_type(), true, get_current_blog_id() );

		$this->redirect( $result['ok'] ? 'default' : ( '' === $result['error'] ? 'failed' : $result['error'] ) );
	}

	/**
	 * Reset ONE control (§16).
	 *
	 * Not currently surfaced as a button in the UI, but it is the API the Customizer reset
	 * links use, and it is tested independently so the three reset operations stay distinct.
	 */
	public function handle_reset_control(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_RESET_CONTROL );

		$key = isset( $_POST['control'] ) ? sanitize_key( wp_unslash( $_POST['control'] ) ) : '';

		$result = $this->catalogue->reset_control( get_current_blog_id(), $key );

		$this->redirect( $result['ok'] ? 'reset' : ( '' === $result['error'] ? 'failed' : $result['error'] ) );
	}

	/**
	 * Save the Design Studio form (§20, §43).
	 *
	 * SECURITY (§26): capability → nonce → CURRENT SITE (never a posted blog id) → per-control
	 * validation → mutate. Only `bb_design_<key>` theme mods are written, through the EXISTING
	 * sanitizer, so a value the schema does not accept is silently discarded rather than stored.
	 *
	 * ARCHITECTURAL NOTE
	 * ------------------
	 * This is NOT a second customization system (§15, §40). It writes exactly the same mods the
	 * Theme's Customizer writes, validated by exactly the same function - it is simply a faster,
	 * more visual front end to them. A control whose key is not in the schema is ignored, so a
	 * forged field name cannot create an arbitrary theme mod.
	 */
	public function handle_save(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_SAVE );

		$blog_id = get_current_blog_id();

		if ( ! function_exists( 'bb_theme_design_schema' ) || ! function_exists( 'bb_theme_sanitize_design_value' ) || ! function_exists( 'bb_theme_design_mod_name' ) ) {
			$this->redirect( 'failed' );
		}

		/* The schema is the authority on which controls exist and how each is validated. */
		$schema = array();

		foreach ( bb_theme_design_schema() as $control ) {

			if ( is_array( $control ) && isset( $control['key'] ) ) {
				$schema[ (string) $control['key'] ] = $control;
			}
		}

		$posted = isset( $_POST['bb_design'] ) && is_array( $_POST['bb_design'] )
			? wp_unslash( $_POST['bb_design'] )
			: array();

		$saved   = 0;
		$cleared = 0;

		foreach ( $posted as $key => $value ) {

			$key = sanitize_key( (string) $key );

			/* A key that is not a real control can never become a theme mod. */
			if ( '' === $key || ! isset( $schema[ $key ] ) ) {
				continue;
			}

			$mod = bb_theme_design_mod_name( $key );

			/* An empty submission means "stop overriding this control". */
			if ( '' === trim( (string) $value ) ) {

				remove_theme_mod( $mod );
				$cleared++;

				continue;
			}

			/* The Theme's own validator decides whether the value is acceptable. */
			$clean = bb_theme_sanitize_design_value( (string) $value, $schema[ $key ] );

			if ( '' === $clean ) {
				continue;
			}

			set_theme_mod( $mod, $clean );
			$saved++;
		}

		$this->redirect( 'saved', array( 'bb_saved' => $saved, 'bb_cleared' => $cleared ) );
	}

	/* =====================================================================
	 * Phase 22 - Section-level design writes (§14-§19, §25, §30, §33)
	 * ================================================================== */

	/**
	 * Save the Section Studio's per-section visual overrides.
	 *
	 * SECURITY (§33): capability → nonce → CURRENT SITE (never a posted blog id) → per-section,
	 * per-token validation → mutate.
	 *
	 * A section type is only accepted when the EXISTING `SectionRegistry` genuinely knows it, so a
	 * forged section name cannot create an arbitrary theme mod. Each token is then validated by
	 * `SectionStyleSchema::sanitize()`, which whitelists colours, choice keywords, numbers and
	 * bounded lengths - a value outside the control's own domain is silently dropped rather than
	 * stored.
	 *
	 * Only `bb_design_section_<type>` mods are written. Section CONTENT and settings
	 * (`_bb_page_sections`) are never touched, because a section's visual treatment is site-wide
	 * presentation, not page content (§29).
	 */
	public function handle_save_sections(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_SAVE_SECTIONS );

		$posted = isset( $_POST['bb_section'] ) && is_array( $_POST['bb_section'] )
			? wp_unslash( $_POST['bb_section'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is validated per-token below.
			: array();

		$known = array();

		foreach ( $this->section_schema->sections() as $type => $section ) {
			$known[ (string) $type ] = true;
		}

		$saved   = 0;
		$changed = 0;

		foreach ( $posted as $type => $tokens ) {

			$type = sanitize_key( (string) $type );

			/* A section the registry does not know can never become a theme mod. */
			if ( '' === $type || ! isset( $known[ $type ] ) || ! is_array( $tokens ) ) {
				continue;
			}

			/* Normalise each token name before it reaches the validator. */
			$clean = array();

			foreach ( $tokens as $token => $value ) {

				$token = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $token ) );

				if ( '' === $token || 0 !== strpos( $token, '--bb-section-' ) ) {
					continue;
				}

				$clean[ $token ] = $value;
			}

			$count = $this->section_schema->save( $type, $clean );

			if ( $count > 0 ) {
				$changed++;
			}

			$saved += $count;
		}

		$this->redirect( 'saved', array( 'bb_saved' => $saved, 'bb_sections' => $changed ) );
	}

	/**
	 * Reset ONE section's visual overrides (§30).
	 *
	 * Returns the section to the design/global state. It cannot touch any other section, and it
	 * cannot touch content.
	 */
	public function handle_reset_section(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_RESET_SECTION );

		$type = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';

		$ok = $this->section_schema->reset( $type );

		$this->redirect( $ok ? 'section_reset' : 'invalid' );
	}

	/**
	 * Reset EVERY section's visual overrides for this site (§30).
	 */
	public function handle_reset_sections(): void {

		$this->authorize();

		check_admin_referer( self::ACTION_RESET_SECTIONS );

		$removed = $this->section_schema->reset_all();

		$this->redirect( 'sections_reset', array( 'bb_removed' => $removed ) );
	}
}
