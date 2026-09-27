<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section Studio UI (Phase 22 §14-§19).
 *
 * Renders ONE visual control panel per REGISTERED section, discovered from the
 * existing `SectionRegistry`. It is the piece that turns "a site-wide palette"
 * into "control every section of my website".
 *
 * WHAT IT IS
 * ----------
 * A RENDERER, like `DesignStudioUI`. It owns no storage, no tokens and no
 * schema: every control it draws comes from `SectionStyleSchema::groups()`,
 * every value it writes goes through `SectionStyleSchema::save()` (and therefore
 * through that class's own validator).
 *
 * WHAT IT IS NOT
 * --------------
 * It does not know a single section name. It never writes
 * `if ( 'lawyers' === $type )`. A section appears in the picker because it was
 * REGISTERED, and its panel is built from the GENERIC control catalogue plus
 * two registry-derived facts:
 *
 *   - whether the section renders cards (`SectionStyleSchema::is_card_capable()`),
 *     which decides whether the card group is shown;
 *   - which layout variants it declares (read from the EXISTING Phase 11
 *     `SectionVariants` registry), which decides whether a layout picker is
 *     shown — so the section-variant architecture is REUSED, not duplicated
 *     (§17).
 *
 * UX (§28)
 * --------
 * Cards, not a wall of fields: one card per section, a group of collapsible
 * controls inside it, real colour swatches, named choices with visual bars, and
 * an "overridden" marker so a customer can always see what they changed and
 * reset exactly that.
 */
class SectionStudioUI {

	protected SectionStyleSchema $schema;

	public function __construct( ?SectionStyleSchema $schema = null ) {
		$this->schema = $schema ?: new SectionStyleSchema();
	}

	/**
	 * Render the whole Section Studio.
	 *
	 * @param string $return_url Screen URL for the action forms.
	 * @param string $action     Admin-post action name for saving section styles.
	 */
	public function render( string $return_url, string $action ): void {

		$sections = $this->schema->sections();

		if ( empty( $sections ) ) {
			return;
		}

		$groups = $this->schema->groups();

		?>
		<section class="bb-section-studio" id="bb-section-studio" aria-labelledby="bb-section-studio-title">

			<div class="bb-studio-head">
				<h2 id="bb-section-studio-title" class="bb-studio-title">
					<?php esc_html_e( 'Sections', 'business-builder' ); ?>
				</h2>
				<p class="bb-studio-lede">
					<?php esc_html_e( 'Every section of your website is listed here. Open one to give it its own background, colours, spacing, cards, motion or glass effect — without changing the rest of the site.', 'business-builder' ); ?>
				</p>
			</div>

			<?php $this->render_section_picker( $sections ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-section-studio-form" data-bb-section-form>

				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return_url ); ?>">
				<?php wp_nonce_field( $action ); ?>

				<div class="bb-section-panels">
					<?php
					foreach ( $sections as $type => $section ) {
						$this->render_section_panel( (string) $type, $section, $groups );
					}
					?>
				</div>

				<div class="bb-studio-footer">
					<button type="submit" class="button button-primary button-hero">
						<?php esc_html_e( 'Save section changes', 'business-builder' ); ?>
					</button>

					<span class="bb-studio-footer-note" data-bb-studio-note hidden>
						<?php esc_html_e( 'Unsaved changes', 'business-builder' ); ?>
					</span>
				</div>

			</form>

			<?php $this->render_reset( $return_url ); ?>

		</section>
		<?php
	}

	/**
	 * The section picker: a chip per registered section, used to jump between panels.
	 *
	 * A picker rather than one long scroll, because a site with 16 registered
	 * sections would otherwise be an unreadable wall (§28 "avoid huge
	 * unstructured forms").
	 *
	 * @param array<string, array<string, mixed>> $sections Discovered sections.
	 */
	protected function render_section_picker( array $sections ): void {

		$categories = array();

		foreach ( $sections as $type => $section ) {
			$categories[ (string) $section['category'] ][] = $type;
		}

		?>
		<div class="bb-section-picker" role="navigation" aria-label="<?php esc_attr_e( 'Jump to a section', 'business-builder' ); ?>">

			<?php foreach ( $categories as $category => $types ) : ?>

				<div class="bb-section-picker-group">
					<span class="bb-section-picker-category"><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $category ) ) ); ?></span>

					<div class="bb-section-picker-chips">
						<?php foreach ( $types as $type ) : ?>

							<?php
							$section    = $sections[ $type ];
							$overridden = array() !== $this->schema->overrides( (string) $type );
							?>

							<button
								type="button"
								class="bb-section-chip<?php echo $overridden ? ' is-overridden' : ''; ?>"
								data-bb-section-jump="<?php echo esc_attr( (string) $type ); ?>"
								aria-controls="bb-section-panel-<?php echo esc_attr( (string) $type ); ?>"
							>
								<span class="dashicons <?php echo esc_attr( (string) $section['icon'] ); ?>" aria-hidden="true"></span>
								<span><?php echo esc_html( (string) $section['label'] ); ?></span>

								<?php if ( $overridden ) : ?>
									<span class="bb-section-chip-dot" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Customized', 'business-builder' ); ?></span>
								<?php endif; ?>
							</button>

						<?php endforeach; ?>
					</div>
				</div>

			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * One section's control panel.
	 *
	 * @param string               $type    Section type.
	 * @param array<string, mixed> $section Section descriptor.
	 * @param array<string, mixed> $groups  Control groups.
	 */
	protected function render_section_panel( string $type, array $section, array $groups ): void {

		$overrides = $this->schema->overrides( $type );
		$has_cards = ! empty( $section['card'] );
		$variants  = isset( $section['variants'] ) && is_array( $section['variants'] ) ? $section['variants'] : array();

		?>
		<section
			class="bb-section-panel<?php echo $overrides ? ' is-overridden' : ''; ?>"
			id="bb-section-panel-<?php echo esc_attr( $type ); ?>"
			data-bb-section-panel="<?php echo esc_attr( $type ); ?>"
			hidden
		>

			<header class="bb-section-panel-head">
				<div>
					<h3 class="bb-section-panel-title">
						<span class="dashicons <?php echo esc_attr( (string) $section['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( (string) $section['label'] ); ?>
					</h3>

					<?php if ( '' !== (string) $section['description'] ) : ?>
						<p class="bb-section-panel-desc"><?php echo esc_html( (string) $section['description'] ); ?></p>
					<?php endif; ?>

					<p class="bb-section-panel-meta">
						<?php
						printf(
							/* translators: %s: section type slug. */
							esc_html__( 'Applies to every “%s” section on this site.', 'business-builder' ),
							esc_html( (string) $section['label'] )
						);
						?>
					</p>
				</div>

				<?php if ( $overrides ) : ?>
					<span class="bb-design-badge bb-design-badge--active">
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
						<?php
						printf(
							/* translators: %d: number of customizations. */
							esc_html( _n( '%d change', '%d changes', count( $overrides ), 'business-builder' ) ),
							(int) count( $overrides )
						);
						?>
					</span>
				<?php endif; ?>
			</header>

			<?php
			/*
			 * LAYOUT VARIANTS (§17): rendered only when the section actually declares
			 * a non-default variant. The options come from the EXISTING Phase 11
			 * registry, so no variant list is duplicated here — and a pack that adds
			 * a new layout gets the picker for free.
			 */
			if ( count( $variants ) > 1 ) {
				$this->render_variant_picker( $type, $variants, $section );
			}
			?>

			<div class="bb-section-panel-groups">

				<?php foreach ( $groups as $group_key => $group ) : ?>

					<?php
					/* A card-only group is hidden for a section that renders no cards. */
					if ( ! empty( $group['cards'] ) && ! $has_cards ) {
						continue;
					}

					$controls = array();

					foreach ( $group['controls'] as $control ) {

						if ( ! empty( $control['cards'] ) && ! $has_cards ) {
							continue;
						}

						$controls[] = $control;
					}

					if ( empty( $controls ) ) {
						continue;
					}
					?>

					<details class="bb-section-group"<?php echo 'layout' === $group_key ? ' open' : ''; ?>>
						<summary class="bb-section-group-summary">
							<span><?php echo esc_html( (string) $group['label'] ); ?></span>

							<?php
							$count = 0;

							foreach ( $controls as $control ) {
								if ( isset( $overrides[ (string) $control['token'] ] ) ) {
									$count++;
								}
							}

							if ( $count > 0 ) :
								?>
								<span class="bb-section-group-count">
									<?php
									printf(
										/* translators: %d: number of changed controls in this group. */
										esc_html( _n( '%d changed', '%d changed', $count, 'business-builder' ) ),
										(int) $count
									);
									?>
								</span>
							<?php endif; ?>
						</summary>

						<div class="bb-section-group-body">

							<?php if ( ! empty( $group['help'] ) ) : ?>
								<p class="bb-studio-help"><?php echo esc_html( (string) $group['help'] ); ?></p>
							<?php endif; ?>

							<?php foreach ( $controls as $control ) : ?>
								<?php $this->render_control( $type, $control, $overrides ); ?>
							<?php endforeach; ?>

						</div>
					</details>

				<?php endforeach; ?>

			</div>

			<footer class="bb-section-panel-foot">
				<button
					type="button"
					class="button bb-section-reset"
					data-bb-section-reset="<?php echo esc_attr( $type ); ?>"
				>
					<?php esc_html_e( 'Reset this section', 'business-builder' ); ?>
				</button>

				<span class="bb-studio-help">
					<?php esc_html_e( 'Returns this section to the design and site settings. Nothing else is affected.', 'business-builder' ); ?>
				</span>

				<?php if ( $overrides ) : ?>
					<button
						type="button"
						class="button-link bb-section-clear"
						data-bb-section-clear="<?php echo esc_attr( $type ); ?>"
					>
						<?php esc_html_e( 'Clear these fields', 'business-builder' ); ?>
					</button>
				<?php endif; ?>
			</footer>

		</section>
		<?php
	}

	/**
	 * The section's layout variant picker (§17).
	 *
	 * REUSES the Phase 11 variant architecture: the options come from
	 * `bb_section_variant_options()`, which reads the registered variants. The
	 * selected value is written into the section's own settings through the
	 * existing `variant` setting, so the existing resolver keeps deciding.
	 *
	 * @param string               $type     Section type.
	 * @param array<string, string> $variants slug => label.
	 * @param array<string, mixed>  $section  Section descriptor.
	 */
	protected function render_variant_picker( string $type, array $variants, array $section ): void {

		/*
		 * The variant is a PAGE-LEVEL section setting (which layout this instance
		 * uses), not a site-wide visual token — so it is presented here as a
		 * preview/reference of the available layouts rather than as a site-wide
		 * write. Editing it per page happens in the Page Builder, which is the
		 * existing, correct home for it (§29: do not put per-page settings into
		 * global site settings).
		 */
		?>
		<div class="bb-section-variants">
			<span class="bb-section-variants-label">
				<?php esc_html_e( 'Layouts available for this section', 'business-builder' ); ?>
			</span>

			<div class="bb-section-variants-list">
				<?php foreach ( $variants as $slug => $label ) : ?>
					<span class="bb-section-variant">
						<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
						<?php echo esc_html( (string) $label ); ?>
					</span>
				<?php endforeach; ?>
			</div>

			<p class="bb-studio-help">
				<?php esc_html_e( 'Choose which of these a page uses while editing that page in the Page Builder. The visual settings below apply to all of them.', 'business-builder' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * One control, rendered visually (§28).
	 *
	 * Colour controls render as a swatch + picker; choices render as segmented
	 * buttons with an explicit "Inherit" option; numbers render as a slider.
	 * No control is ever presented as a raw CSS field.
	 *
	 * @param string                $type      Section type.
	 * @param array<string, mixed>  $control   Control definition.
	 * @param array<string, string> $overrides Stored overrides.
	 */
	protected function render_control( string $type, array $control, array $overrides ): void {

		$key      = (string) $control['key'];
		$token    = (string) $control['token'];
		$value    = isset( $overrides[ $token ] ) ? (string) $overrides[ $token ] : '';
		$name     = 'bb_section[' . $type . '][' . $token . ']';
		$input_id = 'bb-section-' . $type . '-' . $key;
		$changed  = '' !== $value;

		$type_attr = (string) $control['type'];

		?>
		<div class="bb-section-control bb-section-control--<?php echo esc_attr( $type_attr ); ?><?php echo $changed ? ' is-changed' : ''; ?>" data-bb-section-control="<?php echo esc_attr( $token ); ?>">

			<label class="bb-section-control-label" for="<?php echo esc_attr( $input_id ); ?>">
				<?php echo esc_html( (string) $control['label'] ); ?>
			</label>

			<?php if ( 'color' === $type_attr ) : ?>

				<div class="bb-color-row">
					<?php
					/*
					 * A colour input cannot express "no override", so an explicit
					 * empty checkbox is rendered beside it. That keeps the
					 * "inherit vs. override" distinction visible and reversible
					 * (§16: the system must distinguish global defaults from
					 * section overrides).
					 */
					?>
					<input
						type="color"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( '' !== $value ? $value : '#000000' ); ?>"
						class="bb-color-input"
						data-bb-section-color="<?php echo esc_attr( $token ); ?>"
					>

					<code class="bb-color-hex" data-bb-section-hex="<?php echo esc_attr( $token ); ?>">
						<?php echo esc_html( '' !== $value ? strtoupper( $value ) : __( 'Inherited', 'business-builder' ) ); ?>
					</code>

					<label class="bb-section-inherit">
						<input
							type="checkbox"
							value=""
							name="<?php echo esc_attr( $name ); ?>"
							data-bb-section-inherit="<?php echo esc_attr( $token ); ?>"
							<?php checked( '' === $value ); ?>
						>
						<span><?php esc_html_e( 'Use the design colour', 'business-builder' ); ?></span>
					</label>
				</div>

			<?php elseif ( 'select' === $type_attr ) : ?>

				<div class="bb-choice-options" role="radiogroup" aria-label="<?php echo esc_attr( (string) $control['label'] ); ?>">
					<?php foreach ( (array) $control['options'] as $option_value => $option_label ) : ?>

						<?php $selected = ( (string) $option_value === $value ); ?>

						<label class="bb-choice-option<?php echo $selected ? ' is-selected' : ''; ?>">
							<input
								type="radio"
								name="<?php echo esc_attr( $name ); ?>"
								value="<?php echo esc_attr( (string) $option_value ); ?>"
								data-bb-section-choice="<?php echo esc_attr( $token ); ?>"
								<?php checked( $selected ); ?>
							>
							<span><?php echo esc_html( (string) $option_label ); ?></span>
						</label>

					<?php endforeach; ?>
				</div>

			<?php else : ?>

				<?php
				$min  = isset( $control['min'] ) ? (float) $control['min'] : 0;
				$max  = isset( $control['max'] ) ? (float) $control['max'] : 100;
				$step = isset( $control['step'] ) && '' !== $control['step'] ? (float) $control['step'] : 1;
				$unit = (string) ( $control['unit'] ?? '' );

				$numeric = (float) preg_replace( '/[^0-9.\-]/', '', $value );
				?>

				<div class="bb-range">
					<span class="bb-range-label">
						<code data-bb-section-range-value="<?php echo esc_attr( $token ); ?>">
							<?php echo esc_html( '' !== $value ? $value : __( 'Inherited', 'business-builder' ) ); ?>
						</code>
					</span>

					<input
						type="range"
						name="<?php echo esc_attr( $name ); ?>"
						min="<?php echo esc_attr( (string) $min ); ?>"
						max="<?php echo esc_attr( (string) $max ); ?>"
						step="<?php echo esc_attr( (string) $step ); ?>"
						value="<?php echo esc_attr( '' !== $value ? (string) $numeric : (string) $max ); ?>"
						data-bb-section-range="<?php echo esc_attr( $token ); ?>"
						data-bb-section-unit="<?php echo esc_attr( $unit ); ?>"
						class="bb-range-input"
						<?php echo '' === $value ? ' data-bb-section-range-unset="1"' : ''; ?>
					>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * The section reset block (§30).
	 *
	 * @param string $return_url Screen URL.
	 */
	protected function render_reset( string $return_url ): void {

		$count = $this->schema->override_count();

		?>
		<div class="bb-studio-reset">
			<h3 class="bb-studio-reset-title"><?php esc_html_e( 'Reset sections', 'business-builder' ); ?></h3>

			<p class="bb-studio-help">
				<?php
				if ( $count > 0 ) {
					printf(
						/* translators: %d: number of customized sections. */
						esc_html( _n( '%d section has its own visual settings.', '%d sections have their own visual settings.', $count, 'business-builder' ) ),
						(int) $count
					);
				} else {
					esc_html_e( 'Every section currently follows your design and site settings.', 'business-builder' );
				}
				?>
			</p>

			<div class="bb-studio-reset-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( DesignPage::ACTION_RESET_SECTIONS ); ?>">
					<input type="hidden" name="bb_return" value="<?php echo esc_attr( $return_url ); ?>">
					<?php wp_nonce_field( DesignPage::ACTION_RESET_SECTIONS ); ?>
					<button type="submit" class="button">
						<?php esc_html_e( 'Reset all section settings', 'business-builder' ); ?>
					</button>
					<span class="bb-studio-reset-note"><?php esc_html_e( 'Keeps your design, colours and typography. Only section-level changes are removed.', 'business-builder' ); ?></span>
				</form>
			</div>
		</div>
		<?php
	}
}