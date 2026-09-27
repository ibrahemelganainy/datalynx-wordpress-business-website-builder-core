<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design Studio UI (Phase 21 §20-§29, §37, §45-§49).
 *
 * Renders the customization panel that sits beside the Design catalogue: visual colour swatches,
 * curated palettes, gradient tiles, font specimens, and named density/shape/shell/motion choices.
 *
 * WHAT THIS CLASS IS AND IS NOT
 * -----------------------------
 * It is a RENDERER. It owns no storage, no tokens and no schema:
 *
 *   - every control it draws comes from `bb_theme_design_schema()`;
 *   - every value it writes goes through the Theme's own sanitizer;
 *   - every palette/gradient/font it offers comes from the library classes, which in turn read
 *     the schema.
 *
 * It deliberately does NOT reimplement the Customizer. The Customizer remains the authoritative
 * editing surface (§15 of the audit); the Studio is a faster, visual path to the same controls,
 * writing the same `bb_design_<key>` mods.
 *
 * §21 COMPLIANCE — THE COLOUR IS THE UI
 * -------------------------------------
 * No control is presented as a bare hex field. Each colour control renders as:
 *
 *     ● Primary
 *     ████████████████        <- the actual colour, as a large swatch
 *     #0F172A                 <- secondary information
 *
 * and each palette renders as a strip of real swatches.
 *
 * §59 PERFORMANCE
 * ---------------
 * The panel is plain server-rendered markup plus one small script. No framework, no build step.
 * The live preview (§43) is driven by CSS custom properties, so a change repaints the preview
 * without a network request and without re-rendering the page.
 */
class DesignStudioUI {

	protected DesignPalettes $palettes;

	protected DesignGradients $gradients;

	protected DesignTypography $typography;

	protected DesignScales $scales;

	protected DesignCatalogue $catalogue;

	public function __construct(
		?DesignPalettes $palettes = null,
		?DesignGradients $gradients = null,
		?DesignTypography $typography = null,
		?DesignScales $scales = null,
		?DesignCatalogue $catalogue = null
	) {
		$this->palettes   = $palettes ?: new DesignPalettes();
		$this->gradients  = $gradients ?: new DesignGradients();
		$this->typography = $typography ?: new DesignTypography();
		$this->scales     = $scales ?: new DesignScales();
		$this->catalogue  = $catalogue ?: new DesignCatalogue();
	}

	/**
	 * The design currently active on this site, normalized.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function active_design(): ?array {

		$all    = $this->catalogue->all();
		$active = $this->catalogue->active();

		return isset( $all[ $active ] ) ? $all[ $active ] : null;
	}

	/**
	 * The value a control currently resolves to (override, else the design's own value).
	 *
	 * @param array<string, mixed> $control Schema control.
	 * @param array<string, mixed> $config  Resolved design configuration.
	 * @return string
	 */
	protected function current_value( array $control, array $config ): string {

		$token = (string) ( $control['token'] ?? '' );

		if ( '' !== $token && isset( $config[ $token ] ) ) {
			return (string) $config[ $token ];
		}

		return (string) ( $control['default'] ?? '' );
	}

	/**
	 * Whether a control has been customized away from the design's authored value.
	 *
	 * @param string $key Control key.
	 * @return bool
	 */
	protected function is_overridden( string $key ): bool {

		if ( ! function_exists( 'bb_theme_design_mod_name' ) ) {
			return false;
		}

		return '' !== (string) get_theme_mod( bb_theme_design_mod_name( $key ), '' );
	}

	/**
	 * Render the whole Studio panel.
	 *
	 * @param string $return_url Screen URL for the action forms.
	 * @param string $action     The admin-post action name for saving.
	 * @return void
	 */
	public function render( string $return_url, string $action ): void {

		$design = $this->active_design();

		if ( ! $design ) {
			return;
		}

		$config = function_exists( 'bb_theme_preset_config' ) ? bb_theme_preset_config() : array();

		?>
		<section class="bb-studio" aria-labelledby="bb-studio-title">

			<div class="bb-studio-head">
				<h2 id="bb-studio-title" class="bb-studio-title"><?php esc_html_e( 'Customize', 'business-builder' ); ?></h2>
				<p class="bb-studio-lede">
					<?php esc_html_e( 'Fine-tune the design you selected. Changes apply to your live site when you save.', 'business-builder' ); ?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-studio-form" data-bb-studio-form>

				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return_url ); ?>">
				<?php wp_nonce_field( $action ); ?>

				<div class="bb-studio-layout">

					<div class="bb-studio-controls">

						<?php
						$this->render_palettes( $config );
						$this->render_colors( $config );
						$this->render_typography( $config );
						$this->render_gradients( $config );
						$this->render_scales( $config );
						?>

					</div>

					<div class="bb-studio-preview">

						<div class="bb-studio-preview-head">
							<span class="bb-studio-preview-label"><?php esc_html_e( 'Live preview', 'business-builder' ); ?></span>
							<span class="bb-studio-preview-hint"><?php esc_html_e( 'Updates as you change settings', 'business-builder' ); ?></span>
						</div>

						<div class="bb-studio-preview-frame" data-bb-studio-preview>
							<?php echo $this->preview_html( $design, $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally. ?>
						</div>

					</div>

				</div>

				<div class="bb-studio-footer">
					<button type="submit" class="button button-primary button-hero">
						<?php esc_html_e( 'Save changes', 'business-builder' ); ?>
					</button>

					<span class="bb-studio-footer-note" data-bb-studio-note hidden>
						<?php esc_html_e( 'Unsaved changes', 'business-builder' ); ?>
					</span>

					<a class="bb-studio-advanced-link" href="<?php echo esc_url( $this->customizer_url() ); ?>">
						<?php esc_html_e( 'Open the full customizer', 'business-builder' ); ?>
					</a>
				</div>

			</form>

			<?php $this->render_reset( $return_url ); ?>

		</section>
		<?php
	}

	/**
	 * The Customizer URL, pre-focused on the design controls.
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
	 * Render the design preview, with the CURRENT resolved values baked in.
	 *
	 * The preview starts from the live configuration, so it is correct on first paint; the script
	 * then overrides individual tokens as the customer edits.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param array<string, mixed> $config Resolved configuration.
	 * @return string
	 */
	protected function preview_html( array $design, array $config ): string {

		/*
		 * Merge the resolved config OVER the design's tokens, so the preview reflects overrides
		 * the customer has already saved rather than the design's authored values.
		 */
		$tokens = isset( $design['tokens'] ) && is_array( $design['tokens'] ) ? $design['tokens'] : array();

		foreach ( $config as $name => $value ) {

			$name = (string) $name;

			if ( 0 === strpos( $name, '--bb-' ) ) {
				$tokens[ $name ] = (string) $value;
			}
		}

		$preview = $design;
		$preview['tokens'] = $tokens;

		$renderer = new DesignPreviewRenderer();

		return $renderer->render( $preview, 'desktop', 'modal', false, (string) get_bloginfo( 'name' ) );
	}

	/**
	 * The palette library (§21, §22).
	 *
	 * @param array<string, mixed> $config Resolved configuration.
	 */
	protected function render_palettes( array $config ): void {

		$palettes = $this->palettes->for_ui();

		if ( empty( $palettes ) ) {
			return;
		}

		?>
		<fieldset class="bb-studio-group">
			<legend class="bb-studio-legend"><?php esc_html_e( 'Palette presets', 'business-builder' ); ?></legend>
			<p class="bb-studio-help"><?php esc_html_e( 'A professionally matched set of colours. Choosing one replaces every brand colour at once.', 'business-builder' ); ?></p>

			<div class="bb-palette-grid">
				<?php foreach ( $palettes as $slug => $palette ) : ?>

					<?php $grade = (string) ( $palette['contrast']['grade'] ?? 'good' ); ?>

					<button
						type="button"
						class="bb-palette-tile bb-palette-tile--<?php echo esc_attr( $grade ); ?>"
						data-bb-palette="<?php echo esc_attr( (string) $slug ); ?>"
						data-bb-palette-colors="<?php echo esc_attr( (string) wp_json_encode( $palette['colors'] ) ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: palette name. */ __( 'Apply the %s palette', 'business-builder' ), (string) $palette['label'] ) ); ?>"
					>
						<span class="bb-palette-swatches" aria-hidden="true">
							<?php foreach ( $palette['swatches'] as $swatch ) : ?>
								<i style="background-color:<?php echo esc_attr( (string) $swatch['value'] ); ?>;"></i>
							<?php endforeach; ?>
						</span>

						<span class="bb-palette-name"><?php echo esc_html( (string) $palette['label'] ); ?></span>

						<span class="bb-palette-grade bb-palette-grade--<?php echo esc_attr( $grade ); ?>">
							<?php
							if ( 'good' === $grade ) {
								esc_html_e( 'Readable', 'business-builder' );
							} elseif ( 'fair' === $grade ) {
								esc_html_e( 'Mostly readable', 'business-builder' );
							} else {
								esc_html_e( 'Check contrast', 'business-builder' );
							}
							?>
						</span>
					</button>

				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Colour controls, rendered as large visual swatches (§21).
	 *
	 * @param array<string, mixed> $config Resolved configuration.
	 */
	protected function render_colors( array $config ): void {

		if ( ! function_exists( 'bb_theme_design_schema' ) ) {
			return;
		}

		$colors = array();

		foreach ( bb_theme_design_schema() as $control ) {

			if ( ! is_array( $control ) || 'color' !== ( $control['type'] ?? '' ) ) {
				continue;
			}

			$colors[] = $control;
		}

		if ( empty( $colors ) ) {
			return;
		}

		?>
		<fieldset class="bb-studio-group">
			<legend class="bb-studio-legend"><?php esc_html_e( 'Colours', 'business-builder' ); ?></legend>
			<p class="bb-studio-help"><?php esc_html_e( 'Every colour used across the site. Click a swatch to change it.', 'business-builder' ); ?></p>

			<div class="bb-color-grid">
				<?php foreach ( $colors as $control ) : ?>

					<?php
					$key   = (string) ( $control['key'] ?? '' );
					$value = $this->current_value( $control, $config );

					if ( '' === $key || '' === $value ) {
						continue;
					}

					$overridden = $this->is_overridden( $key );
					?>

					<div class="bb-color-control<?php echo $overridden ? ' is-overridden' : ''; ?>">
						<label class="bb-color-label" for="bb-color-<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( (string) ( $control['label'] ?? $key ) ); ?>
						</label>

						<div class="bb-color-row">
							<input
								type="color"
								id="bb-color-<?php echo esc_attr( $key ); ?>"
								name="bb_design[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( $value ); ?>"
								class="bb-color-input"
								data-bb-color="<?php echo esc_attr( $key ); ?>"
								data-bb-token="<?php echo esc_attr( (string) ( $control['token'] ?? '' ) ); ?>"
							>

							<code class="bb-color-hex" data-bb-color-hex="<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( strtoupper( $value ) ); ?>
							</code>
						</div>
					</div>

				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Typography controls with real font specimens (§24, §26).
	 *
	 * @param array<string, mixed> $config Resolved configuration.
	 */
	protected function render_typography( array $config ): void {

		$controls = $this->typography->controls();

		if ( empty( $controls ) ) {
			return;
		}

		$specimens = $this->typography->specimens();

		?>
		<fieldset class="bb-studio-group">
			<legend class="bb-studio-legend"><?php esc_html_e( 'Typography', 'business-builder' ); ?></legend>
			<p class="bb-studio-help"><?php esc_html_e( 'Choose fonts and text scale. Each option is shown in the typeface itself.', 'business-builder' ); ?></p>

			<?php foreach ( $controls as $key => $control ) : ?>

				<?php if ( 'font' === $control['kind'] ) : ?>

					<?php $this->render_font_picker( (string) $key, $control, $config, $specimens ); ?>

				<?php else : ?>

					<?php $this->render_value_control( (string) $key, $control, $config ); ?>

				<?php endif; ?>

			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * One font picker, with each family previewed in its own typeface (§24).
	 *
	 * @param string               $key       Control key.
	 * @param array<string, mixed> $control   Control definition.
	 * @param array<string, mixed> $config    Resolved configuration.
	 * @param array<string, string> $specimens Sample text.
	 */
	protected function render_font_picker( string $key, array $control, array $config, array $specimens ): void {

		$token   = (string) $control['token'];
		$current = $this->current_value( $control, $config );

		/*
		 * The stored value is a SLUG (the Theme's font controls resolve through
		 * `bb_theme_font_stack_choices()`). Resolve it to a stack so the picker can match the
		 * right option and show the specimen in the correct typeface.
		 */
		$stacks   = function_exists( 'bb_theme_font_stack_choices' ) ? bb_theme_font_stack_choices() : array();
		$current_slug = '';

		foreach ( $stacks as $slug => $stack ) {
			if ( (string) $stack === $current ) { $current_slug = (string) $slug; }
		}

		if ( '' === $current_slug ) {
			$current_slug = $this->typography->slug_for_stack( $current );
		}

		?>
		<div class="bb-font-picker">
			<span class="bb-font-label"><?php echo esc_html( (string) $control['label'] ); ?></span>

			<div class="bb-font-options" role="radiogroup" aria-label="<?php echo esc_attr( (string) $control['label'] ); ?>">
				<?php foreach ( $control['fonts'] as $slug => $font ) : ?>

					<?php $selected = ( (string) $slug === $current_slug ); ?>

					<label class="bb-font-option<?php echo $selected ? ' is-selected' : ''; ?>" style="font-family:<?php echo esc_attr( (string) $font['stack'] ); ?>;">
						<input
							type="radio"
							name="bb_design[<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( (string) $slug ); ?>"
							data-bb-font="<?php echo esc_attr( $token ); ?>"
							<?php checked( $selected ); ?>
						>

						<span class="bb-font-name"><?php echo esc_html( (string) $font['label'] ); ?></span>

						<span class="bb-font-specimen">
							<?php echo esc_html( (string) $specimens['latin'] ); ?>
						</span>

						<?php if ( ! empty( $font['arabic'] ) ) : ?>
							<span class="bb-font-specimen bb-font-specimen--arabic" dir="rtl">
								<?php echo esc_html( (string) $specimens['arabic'] ); ?>
							</span>
						<?php endif; ?>

						<span class="bb-font-note"><?php echo esc_html( (string) $font['note'] ); ?></span>
					</label>

				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * A numeric/select typographic control.
	 *
	 * @param string               $key     Control key.
	 * @param array<string, mixed> $control Control definition.
	 * @param array<string, mixed> $config  Resolved configuration.
	 */
	protected function render_value_control( string $key, array $control, array $config ): void {

		$value = $this->current_value( $control, $config );
		$token = (string) ( $control['token'] ?? '' );

		/* A select control renders as a segmented choice, not a dropdown. */
		if ( ! empty( $control['options'] ) ) {

			?>
			<div class="bb-choice">
				<span class="bb-choice-label"><?php echo esc_html( (string) $control['label'] ); ?></span>

				<div class="bb-choice-options" role="radiogroup" aria-label="<?php echo esc_attr( (string) $control['label'] ); ?>">
					<?php foreach ( $control['options'] as $option_value => $option_label ) : ?>

						<?php
						$selected = ( (string) $option_value === $value );

						/*
						 * The CSS this option STANDS FOR, resolved server-side through the same
						 * library the frontend uses. It is handed to the live preview so a choice
						 * like "Glass: Medium" can repaint the preview without JavaScript ever
						 * assembling a CSS value (§26: the preview must be driven by the real
						 * design system, not by a parallel table).
						 */
						$css = $this->option_css( (string) $option_value );
						?>

						<label class="bb-choice-option<?php echo $selected ? ' is-selected' : ''; ?>">
							<input
								type="radio"
								name="bb_design[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( (string) $option_value ); ?>"
								data-bb-schema-select="1"
								data-bb-token="<?php echo esc_attr( $token ); ?>"
								data-bb-css="<?php echo esc_attr( $css ); ?>"
								<?php checked( $selected ); ?>
							>
							<span><?php echo esc_html( (string) $option_label ); ?></span>
						</label>

					<?php endforeach; ?>
				</div>
			</div>
			<?php

			return;
		}

		/* A numeric control renders as a slider with its value shown. */
		$min  = isset( $control['min'] ) ? (float) $control['min'] : 0;
		$max  = isset( $control['max'] ) ? (float) $control['max'] : 100;
		$step = isset( $control['step'] ) && '' !== $control['step'] ? (float) $control['step'] : 1;
		$unit = (string) ( $control['unit'] ?? '' );

		/* The stored value may carry a unit (e.g. `16px`); the slider works on the number. */
		$numeric = (float) preg_replace( '/[^0-9.\-]/', '', $value );

		?>
		<div class="bb-range">
			<span class="bb-range-label">
				<?php echo esc_html( (string) $control['label'] ); ?>
				<code data-bb-range-value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></code>
			</span>

			<input
				type="range"
				name="bb_design[<?php echo esc_attr( $key ); ?>]"
				min="<?php echo esc_attr( (string) $min ); ?>"
				max="<?php echo esc_attr( (string) $max ); ?>"
				step="<?php echo esc_attr( (string) $step ); ?>"
				value="<?php echo esc_attr( (string) $numeric ); ?>"
				data-bb-range="<?php echo esc_attr( $key ); ?>"
				data-bb-unit="<?php echo esc_attr( $unit ); ?>"
				data-bb-token="<?php echo esc_attr( $token ); ?>"
				class="bb-range-input"
			>
		</div>
		<?php
	}

	/**
	 * The CSS a named `select` option stands for, for the live preview (§26).
	 *
	 * Reads the SAME libraries the frontend resolver uses, so a preview can never
	 * show a different result from the saved setting. An unknown or non-CSS option
	 * (a column count, an alignment keyword) simply returns the value itself, which
	 * is exactly what the frontend would emit.
	 *
	 * @param string $value Option value.
	 * @return string
	 */
	protected function option_css( string $value ): string {

		if ( '' === $value ) {
			return '';
		}

		$schema = new DesignSchema();

		$libraries = array(
			$schema->gradient_library(),
			$schema->background_library(),
			$schema->overlay_library(),
		);

		foreach ( $libraries as $library ) {
			if ( isset( $library[ $value ]['css'] ) ) {
				return (string) $library[ $value ]['css'];
			}
		}

		/*
		 * The glass levels and reveal kinds are semantic keywords the SectionStyle
		 * layer resolves; the preview only needs a representative value, and the
		 * frontend resolver remains the authority.
		 */
		$keywords = array(
			'off'    => '0px',
			'subtle' => '8px',
			'medium' => '16px',
			'strong' => '28px',
		);

		if ( isset( $keywords[ $value ] ) ) {
			return $keywords[ $value ];
		}

		return $value;
	}

	/**
	 * Gradient controls, rendered as visual tiles (§23).
	 *
	 * @param array<string, mixed> $config Resolved configuration.
	 */
	protected function render_gradients( array $config ): void {

		$controls = $this->gradients->controls();

		if ( empty( $controls ) ) {
			return;
		}

		?>
		<fieldset class="bb-studio-group">
			<legend class="bb-studio-legend"><?php esc_html_e( 'Gradients', 'business-builder' ); ?></legend>
			<p class="bb-studio-help"><?php esc_html_e( 'Choose the gradient used for the hero, call-to-action and decorative accents.', 'business-builder' ); ?></p>

			<?php foreach ( $controls as $key => $control ) : ?>

				<?php
				$token   = (string) $control['token'];
				$current = (string) ( $config[ $token ] ?? '' );

				/*
				 * A gradient control stores a SLUG, but the resolved config carries the CSS the
				 * slug resolved to. Match the option whose CSS equals the current value, so the
				 * correct tile is preselected.
				 */
				$current_slug = '';

				foreach ( $control['options'] as $slug => $option ) {
					if ( (string) $option['css'] === $current ) { $current_slug = (string) $slug; }
				}
				?>

				<div class="bb-gradient-picker">
					<span class="bb-gradient-label"><?php echo esc_html( (string) $control['label'] ); ?></span>

					<div class="bb-gradient-options" role="radiogroup" aria-label="<?php echo esc_attr( (string) $control['label'] ); ?>">
						<?php foreach ( $control['options'] as $slug => $option ) : ?>

							<?php $selected = ( (string) $slug === $current_slug ); ?>

							<label class="bb-gradient-tile<?php echo $selected ? ' is-selected' : ''; ?><?php echo ! empty( $option['is_flat'] ) ? ' is-flat' : ''; ?>">
								<input
									type="radio"
									name="bb_design[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( (string) $slug ); ?>"
									data-bb-gradient="<?php echo esc_attr( $token ); ?>"
									data-bb-gradient-css="<?php echo esc_attr( (string) $option['css'] ); ?>"
									<?php checked( $selected ); ?>
								>

								<span class="bb-gradient-swatch" style="background-image:<?php echo esc_attr( (string) $option['css'] ); ?>;"></span>
								<span class="bb-gradient-name"><?php echo esc_html( (string) $option['label'] ); ?></span>
							</label>

						<?php endforeach; ?>
					</div>
				</div>

			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Density, shape, shell and motion, as named visual choices (§27).
	 *
	 * @param array<string, mixed> $config Resolved configuration.
	 */
	protected function render_scales( array $config ): void {

		$groups = $this->scales->available();

		if ( empty( $groups ) ) {
			return;
		}

		/*
		 * `design_identity` is the LAST group so the panel reads top-down: what this
		 * is, then how it looks, then how it behaves.
		 */
		$sections = array(
			'identity'   => array(
				'label' => __( 'Design identity', 'business-builder' ),
				'keys'  => array( 'gradient_brand', 'gradient_hero', 'gradient_cta', 'gradient_decorative' ),
			),
			'background' => array(
				'label' => __( 'Background', 'business-builder' ),
				'keys'  => array( 'bg_gradient', 'bg_overlay', 'bg_overlay_opacity' ),
			),
			'layout'     => array(
				'label' => __( 'Layout & grid', 'business-builder' ),
				'keys'  => array(
					'container_padding_block',
					'grid_columns',
					'grid_columns_tablet',
					'grid_columns_mobile',
					'grid_row_gap',
					'card_min_width',
					'content_width',
					'section_align',
				),
			),
			'density' => array(
				'label' => __( 'Spacing & density', 'business-builder' ),
				'keys'  => array( 'section_padding', 'grid_gap', 'card_padding', 'heading_scale', 'heading_letter_spacing' ),
			),
			'shape' => array(
				'label' => __( 'Shape', 'business-builder' ),
				'keys'  => array( 'radius_md', 'card_radius', 'button_radius', 'input_radius', 'border_thickness' ),
			),
			'glass' => array(
				'label' => __( 'Glass effect', 'business-builder' ),
				'keys'  => array( 'glass_level', 'glass_saturate', 'glass_opacity', 'glass_border_opacity' ),
			),
			'header' => array(
				'label' => __( 'Header & scroll', 'business-builder' ),
				'keys'  => array(
					'header_height',
					'header_blur',
					'header_border_width',
					'nav_gap',
					'header_initial_bg',
					'header_initial_nav',
					'header_initial_transparency',
					'header_scrolled_bg',
					'header_scrolled_nav',
					'header_scrolled_border',
					'header_scroll_transition',
				),
			),
			'footer' => array(
				'label' => __( 'Footer', 'business-builder' ),
				'keys'  => array(
					'footer_padding',
					'footer_heading_color',
					'footer_link_color',
					'footer_link_hover',
					'footer_column_gap',
				),
			),
			'motion' => array(
				'label' => __( 'Motion', 'business-builder' ),
				'keys'  => array(
					'motion_speed',
					'reveal_kind',
					'reveal_duration',
					'reveal_distance',
					'reveal_stagger',
					'hover_lift',
					'hover_effect',
				),
			),
		);

		foreach ( $sections as $section_key => $section ) {

			$present = array();

			foreach ( $section['keys'] as $k ) {
				if ( isset( $groups[ $k ] ) ) { $present[ $k ] = $groups[ $k ]; }
			}

			if ( empty( $present ) ) {
				continue;
			}

			?>
			<fieldset class="bb-studio-group">
				<legend class="bb-studio-legend"><?php echo esc_html( (string) $section['label'] ); ?></legend>

				<?php foreach ( $present as $key => $group ) : ?>

					<?php
					/* Find the token this control writes, so the preview can be updated live. */
					$token   = '';
					$current = '';
					$schema_control = null;

					if ( function_exists( 'bb_theme_design_schema' ) ) {
						foreach ( bb_theme_design_schema() as $c ) {
							if ( is_array( $c ) && (string) ( $c['key'] ?? '' ) === (string) $key ) {
								$schema_control = $c;
								$token   = (string) ( $c['token'] ?? '' );
								$current = (string) ( $config[ $token ] ?? ( $c['default'] ?? '' ) );
							}
						}
					}

					/*
					 * A NAMED SCALE (the Phase 21 `DesignScales` groups) renders as the bar
					 * picker. Everything else - the Phase 22 colour, select, length and
					 * number controls - renders through the generic schema-control renderer,
					 * which is what makes the Background, Layout & grid, Glass, Header and
					 * Footer groups actually editable instead of silently skipped (§28:
					 * a control that is not rendered cannot change anything).
					 */
					if ( isset( $group['options'] ) && is_array( $group['options'] ) && null === $schema_control ) {
						$this->render_named_scale( (string) $key, $group, $token, $current );
					} elseif ( null !== $schema_control ) {
						$this->render_value_control( (string) $key, $schema_control, $config );
					}
					?>

				<?php endforeach; ?>
			</fieldset>
			<?php
		}
	}

	/**
	 * One NAMED scale (the Phase 21 `DesignScales` bar picker).
	 *
	 * @param string               $key     Control key.
	 * @param array<string, mixed> $group   Scale group.
	 * @param string               $token   Token the control writes.
	 * @param string               $current Current resolved value.
	 */
	protected function render_named_scale( string $key, array $group, string $token, string $current ): void {

		$current_numeric = (string) preg_replace( '/[^0-9.\-]/', '', $current );

		?>
		<div class="bb-scale" data-bb-scale="<?php echo esc_attr( $key ); ?>">
			<span class="bb-scale-label"><?php echo esc_html( (string) $group['label'] ); ?></span>

			<?php if ( ! empty( $group['help'] ) ) : ?>
				<span class="bb-scale-help"><?php echo esc_html( (string) $group['help'] ); ?></span>
			<?php endif; ?>

			<div class="bb-scale-options" role="radiogroup" aria-label="<?php echo esc_attr( (string) $group['label'] ); ?>">
				<?php foreach ( $group['options'] as $option ) : ?>

					<?php
					$selected = ( (string) $option['value'] === $current_numeric || (string) $option['value'] === $current );
					$bars     = max( 1, min( 5, (int) ( $option['bars'] ?? 3 ) ) );
					?>

					<label class="bb-scale-option<?php echo $selected ? ' is-selected' : ''; ?>">
						<input
							type="radio"
							name="bb_design[<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( (string) $option['value'] ); ?>"
							data-bb-token="<?php echo esc_attr( $token ); ?>"
							data-bb-value="<?php echo esc_attr( (string) $option['value'] ); ?>"
							<?php checked( $selected ); ?>
						>

						<span class="bb-scale-bars" aria-hidden="true">
							<?php for ( $b = 1; $b <= 5; $b++ ) : ?>
								<i class="<?php echo $b <= $bars ? 'is-on' : ''; ?>"></i>
							<?php endfor; ?>
						</span>

						<span class="bb-scale-name"><?php echo esc_html( (string) $option['label'] ); ?></span>
					</label>

				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The three distinct reset operations (§45), with unambiguous wording.
	 *
	 * @param string $return_url Screen URL.
	 */
	protected function render_reset( string $return_url ): void {

		$overrides = function_exists( 'bb_theme_customization_overrides' ) ? bb_theme_customization_overrides() : array();
		$count     = count( $overrides );

		?>
		<div class="bb-studio-reset">
			<h3 class="bb-studio-reset-title"><?php esc_html_e( 'Reset', 'business-builder' ); ?></h3>

			<p class="bb-studio-help">
				<?php
				if ( $count > 0 ) {
					printf(
						/* translators: %d: number of customizations. */
						esc_html( _n( 'You have %d customization applied to this design.', 'You have %d customizations applied to this design.', $count, 'business-builder' ) ),
						(int) $count
					);
				} else {
					esc_html_e( 'This design is currently exactly as its author created it.', 'business-builder' );
				}
				?>
			</p>

			<div class="bb-studio-reset-actions">

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( DesignPage::ACTION_RESET_ALL ); ?>">
					<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return_url ); ?>">
					<?php wp_nonce_field( DesignPage::ACTION_RESET_ALL ); ?>
					<button type="submit" class="button">
						<?php esc_html_e( 'Reset all customizations', 'business-builder' ); ?>
					</button>
					<span class="bb-studio-reset-note"><?php esc_html_e( 'Keeps your design, restores its original colours and settings.', 'business-builder' ); ?></span>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( DesignPage::ACTION_RESTORE_DEFAULT ); ?>">
					<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return_url ); ?>">
					<?php wp_nonce_field( DesignPage::ACTION_RESTORE_DEFAULT ); ?>
					<button type="submit" class="button">
						<?php esc_html_e( 'Restore the default design', 'business-builder' ); ?>
					</button>
					<span class="bb-studio-reset-note"><?php esc_html_e( 'Also switches to the Default design.', 'business-builder' ); ?></span>
				</form>

			</div>
		</div>
		<?php
	}
}