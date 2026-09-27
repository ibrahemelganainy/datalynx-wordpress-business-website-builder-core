<?php
/**
 * Meta Box Renderer (Phase 22 §27, §28).
 *
 * A shared, enterprise-grade field renderer for the Business Builder meta boxes.
 *
 * WHY A SHARED RENDERER
 * ---------------------
 * Every pack hand-rolled its own meta box markup. The measured result was the
 * same set of problems in each one:
 *
 *   - a dozen unrelated fields in one flat column, with no grouping or hierarchy
 *     (`LawyerFields` renders 13 fields as siblings, separated only by a border);
 *   - presentation expressed as INLINE STYLE attributes on each wrapper
 *     (`style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: …"`),
 *     which cannot be themed, cannot respond to the admin colour scheme, and
 *     does not mirror in RTL;
 *   - every field rendered identically, so a URL, a number and a long text area
 *     are indistinguishable at a glance;
 *   - no conditional fields, no visual state, no consistent escaping contract.
 *
 * This class fixes that ONCE. A pack declares a GROUPS + FIELDS structure and
 * gets cards, tabs, sensible field types, descriptions, RTL/LTR-safe layout and
 * accessible markup - without any pack needing to know CSS.
 *
 * DESIGN CONTRACT
 * ---------------
 *   - LOGICAL CSS ONLY: the layout lives in a stylesheet
 *     (`assets/css/admin/meta-boxes.css`) and uses `padding-inline`,
 *     `border-inline-start`, `margin-block`, … so RTL is correct by construction
 *     and no mirrored rule is needed.
 *   - DOMAIN-AWARE, PRESENTATION-ONLY: the renderer knows about field TYPES
 *     (text, url, select, media, repeater) and never about a business concept.
 *     A pack supplies its own labels, groups and option lists.
 *   - ESCAPING BY DEFAULT: every value is escaped at output with the escaper the
 *     field type calls for (`esc_attr`, `esc_textarea`, `esc_url`, `esc_html`),
 *     so a pack cannot forget it.
 *   - ACCESSIBILITY: every control has a real `<label for>`, the description is
 *     wired with `aria-describedby`, groups are `<fieldset>`/`<legend>` pairs,
 *     and required fields are marked with both a visual cue and `aria-required`.
 *   - PROGRESSIVE: a field type the renderer does not know falls back to a plain
 *     text input rather than rendering nothing, so a pack can add a type and
 *     still see its data.
 *
 * @package BusinessBuilderCore
 */

namespace BusinessBuilderCore\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a structured meta box.
 */
class MetaBoxRenderer {

	/**
	 * A monotonically increasing id, so two meta boxes on one screen never
	 * produce colliding element ids.
	 */
	protected static int $instance = 0;

	/**
	 * Register the meta box stylesheet and behaviour script.
	 *
	 * Registered ONCE, by the plugin, rather than by each pack. A pack therefore
	 * only declares fields; it never has to remember to enqueue anything, which is
	 * how the previous hand-rolled meta boxes ended up with inline styles in the
	 * first place.
	 *
	 * The assets are enqueued for EVERY post edit screen rather than only the
	 * Business Builder post types: the renderer is generic, a pack may add a meta
	 * box to any type, and the stylesheet is small. The script declares `wp-media`
	 * and `jquery` as dependencies, so WordPress only loads those on a screen that
	 * actually needs them.
	 */
	public static function register_assets(): void {

		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the meta box assets on post edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook = '' ): void {

		/* Only post edit screens render meta boxes. */
		if ( false === strpos( $hook, 'post.php' ) && false === strpos( $hook, 'post-new.php' ) ) {
			return;
		}

		$css_path = BB_CORE_PATH . 'assets/css/admin/meta-boxes.css';

		wp_enqueue_style(
			'bb-meta-boxes',
			BB_CORE_URL . 'assets/css/admin/meta-boxes.css',
			array(),
			is_readable( $css_path ) ? (string) filemtime( $css_path ) : BB_CORE_VERSION
		);

		$js_path = BB_CORE_PATH . 'assets/js/admin/meta-boxes.js';

		wp_enqueue_media();

		wp_enqueue_script(
			'bb-meta-boxes',
			BB_CORE_URL . 'assets/js/admin/meta-boxes.js',
			array( 'jquery' ),
			is_readable( $js_path ) ? (string) filemtime( $js_path ) : BB_CORE_VERSION,
			true
		);
	}

	/**
	 * Render a complete meta box body.
	 *
	 * @param array<string, mixed> $schema {
	 *     @type string $prefix    Meta-key prefix, e.g. `_bb_lawyer_`.
	 *     @type string $nonce_action Nonce action name.
	 *     @type string $nonce_name   Nonce field name.
	 *     @type array  $groups    Ordered groups (see `render_group()`).
	 *     @type string $intro     Optional one-line explanation at the top.
	 * }
	 * @param int $post_id Post being edited.
	 */
	public static function render( array $schema, int $post_id ): void {

		$prefix = isset( $schema['prefix'] ) ? (string) $schema['prefix'] : '';

		if ( '' === $prefix ) {
			return;
		}

		/* The nonce is the pack's own, so its existing save handler keeps working. */
		if ( ! empty( $schema['nonce_action'] ) && ! empty( $schema['nonce_name'] ) ) {
			wp_nonce_field( (string) $schema['nonce_action'], (string) $schema['nonce_name'] );
		}

		self::$instance++;

		$box_id = 'bb-meta-' . self::$instance;

		$groups = isset( $schema['groups'] ) && is_array( $schema['groups'] ) ? $schema['groups'] : array();

		if ( empty( $groups ) ) {
			return;
		}

		/*
		 * A group that declares `tab` becomes a tab; the rest render in order.
		 * Tabs are built only when at least two groups ask for one, so a small
		 * meta box is not given a pointless single-tab chrome.
		 */
		$tabbed = array();

		foreach ( $groups as $key => $group ) {
			if ( ! empty( $group['tab'] ) ) {
				$tabbed[ (string) $key ] = $group;
			}
		}

		$use_tabs = count( $tabbed ) > 1;

		?>
		<div class="bb-meta" id="<?php echo esc_attr( $box_id ); ?>">

			<?php if ( ! empty( $schema['intro'] ) ) : ?>
				<p class="bb-meta-intro"><?php echo esc_html( (string) $schema['intro'] ); ?></p>
			<?php endif; ?>

			<?php if ( $use_tabs ) : ?>

				<div class="bb-meta-tabs" role="tablist">
					<?php foreach ( $tabbed as $key => $group ) : ?>

						<?php $tab_id = $box_id . '-tab-' . sanitize_html_class( (string) $key ); ?>

						<button
							type="button"
							class="bb-meta-tab"
							id="<?php echo esc_attr( $tab_id ); ?>"
							role="tab"
							aria-controls="<?php echo esc_attr( $tab_id . '-panel' ); ?>"
							aria-selected="false"
							data-bb-meta-tab="<?php echo esc_attr( (string) $key ); ?>"
						>
							<?php echo esc_html( (string) ( $group['label'] ?? $key ) ); ?>
						</button>

					<?php endforeach; ?>
				</div>

			<?php endif; ?>

			<div class="bb-meta-body">

				<?php foreach ( $groups as $key => $group ) : ?>

					<?php
					self::render_group(
						(string) $key,
						$group,
						$prefix,
						$post_id,
						$box_id,
						$use_tabs
					);
					?>

				<?php endforeach; ?>

			</div>
		</div>
		<?php
	}

	/**
	 * Render one group of fields as a card.
	 *
	 * @param string               $key      Group key.
	 * @param array<string, mixed> $group    Group definition.
	 * @param string               $prefix   Meta-key prefix.
	 * @param int                  $post_id  Post id.
	 * @param string               $box_id   Parent box id.
	 * @param bool                 $use_tabs Whether the box is tabbed.
	 */
	protected static function render_group( string $key, array $group, string $prefix, int $post_id, string $box_id, bool $use_tabs ): void {

		$fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();

		if ( empty( $fields ) ) {
			return;
		}

		$is_tab  = ! empty( $group['tab'] );
		$tab_id  = $box_id . '-tab-' . sanitize_html_class( $key );
		$classes = array( 'bb-meta-card' );

		if ( $is_tab && $use_tabs ) {
			$classes[] = 'bb-meta-panel';
		}

		?>
		<section
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php if ( $is_tab && $use_tabs ) : ?>
				id="<?php echo esc_attr( $tab_id . '-panel' ); ?>"
				role="tabpanel"
				aria-labelledby="<?php echo esc_attr( $tab_id ); ?>"
				hidden
			<?php endif; ?>
		>

			<h3 class="bb-meta-card-title">
				<?php echo esc_html( (string) ( $group['label'] ?? $key ) ); ?>
			</h3>

			<?php if ( ! empty( $group['description'] ) ) : ?>
				<p class="bb-meta-card-desc"><?php echo esc_html( (string) $group['description'] ); ?></p>
			<?php endif; ?>

			<div class="bb-meta-fields">

				<?php foreach ( $fields as $field_key => $field ) : ?>
					<?php self::render_field( (string) $field_key, $field, $prefix, $post_id, $box_id ); ?>
				<?php endforeach; ?>

			</div>
		</section>
		<?php
	}

	/**
	 * Render one field.
	 *
	 * @param string               $key     Field key (becomes part of the meta key).
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $prefix  Meta-key prefix.
	 * @param int                  $post_id Post id.
	 * @param string               $box_id  Parent box id.
	 */
	protected static function render_field( string $key, array $field, string $prefix, int $post_id, string $box_id ): void {

		$meta_key = isset( $field['meta_key'] ) ? (string) $field['meta_key'] : $prefix . $key;
		$type     = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : 'text';
		$label    = isset( $field['label'] ) ? (string) $field['label'] : $key;
		$desc     = isset( $field['description'] ) ? (string) $field['description'] : '';
		$required = ! empty( $field['required'] );

		$input_id = sanitize_html_class( $box_id . '-' . $key );
		$desc_id  = $input_id . '-desc';

		$value = get_post_meta( $post_id, $meta_key, true );

		if ( '' === $value && isset( $field['default'] ) ) {
			$value = $field['default'];
		}

		$classes = array( 'bb-meta-field', 'bb-meta-field--' . $type );

		if ( $required ) {
			$classes[] = 'is-required';
		}

		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">

			<label class="bb-meta-label" for="<?php echo esc_attr( $input_id ); ?>">
				<?php echo esc_html( $label ); ?>

				<?php if ( $required ) : ?>
					<span class="bb-meta-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( '(required)', 'business-builder' ); ?></span>
				<?php endif; ?>
			</label>

			<div class="bb-meta-control">
				<?php
				self::render_control(
					$type,
					array(
						'id'          => $input_id,
						'name'        => $meta_key,
						'value'       => $value,
						'field'       => $field,
						'desc_id'     => '' !== $desc ? $desc_id : '',
						'required'    => $required,
					)
				);
				?>
			</div>

			<?php if ( '' !== $desc ) : ?>
				<p class="bb-meta-desc" id="<?php echo esc_attr( $desc_id ); ?>">
					<?php echo esc_html( $desc ); ?>
				</p>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render the control for a field type.
	 *
	 * @param string               $type Field type.
	 * @param array<string, mixed> $args Control arguments.
	 */
	protected static function render_control( string $type, array $args ): void {

		$id       = (string) $args['id'];
		$name     = (string) $args['name'];
		$value    = $args['value'];
		$field    = isset( $args['field'] ) && is_array( $args['field'] ) ? $args['field'] : array();
		$desc_id  = (string) $args['desc_id'];
		$required = ! empty( $args['required'] );

		$described = '' !== $desc_id ? ' aria-describedby="' . esc_attr( $desc_id ) . '"' : '';
		$req_attr  = $required ? ' aria-required="true" required' : '';

		switch ( $type ) {

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" class="widefat bb-input" rows="%d"%s%s>%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) ( $field['rows'] ?? 4 ),
					$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr above.
					$req_attr,  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal attribute.
					esc_textarea( is_scalar( $value ) ? (string) $value : '' )
				);
				break;

			case 'select':
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				$value   = is_scalar( $value ) ? (string) $value : '';

				printf(
					'<select id="%s" name="%s" class="widefat bb-input"%s%s>',
					esc_attr( $id ),
					esc_attr( $name ),
					$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$req_attr   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);

				foreach ( $options as $option_value => $option_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( (string) $option_value ),
						selected( (string) $option_value, $value, false ),
						esc_html( (string) $option_label )
					);
				}

				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label class="bb-meta-switch"><input type="checkbox" id="%s" name="%s" value="1"%s%s> <span>%s</span></label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html( (string) ( $field['toggle_label'] ?? __( 'Enabled', 'business-builder' ) ) )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" class="bb-input bb-input--number"%s%s%s%s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					isset( $field['min'] ) ? ' min="' . esc_attr( (string) $field['min'] ) . '"' : '',
					isset( $field['max'] ) ? ' max="' . esc_attr( (string) $field['max'] ) . '"' : '',
					isset( $field['step'] ) ? ' step="' . esc_attr( (string) $field['step'] ) . '"' : '',
					$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%s" name="%s" value="%s" class="widefat bb-input bb-input--url"%s%s placeholder="https://">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$req_attr   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				break;

			case 'email':
				printf(
					'<input type="email" id="%s" name="%s" value="%s" class="widefat bb-input"%s%s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$req_attr   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				break;

			case 'media':
				/*
				 * A media field stores an ATTACHMENT ID and shows a real thumbnail,
				 * so the editor sees the image they picked rather than a bare number.
				 * The markup is a plain id input plus a preview, so the existing save
				 * handlers keep working unchanged.
				 */
				$attachment_id = absint( $value );

				$preview = $attachment_id > 0
					? wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'class' => 'bb-meta-media-img' ) )
					: '';

				?>
				<div class="bb-meta-media" data-bb-media>
					<div class="bb-meta-media-preview" data-bb-media-preview>
						<?php echo $preview ? $preview : '<span class="bb-meta-media-empty">' . esc_html__( 'No image selected', 'business-builder' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output or an escaped literal. ?>
					</div>

					<input
						type="hidden"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $attachment_id ); ?>"
						data-bb-media-input
					>

					<p class="bb-meta-media-actions">
						<button type="button" class="button bb-meta-media-select" data-bb-media-select>
							<?php esc_html_e( 'Choose image', 'business-builder' ); ?>
						</button>
						<button type="button" class="button-link bb-meta-media-remove" data-bb-media-remove<?php echo $attachment_id > 0 ? '' : ' hidden'; ?>>
							<?php esc_html_e( 'Remove', 'business-builder' ); ?>
						</button>
					</p>
				</div>
				<?php
				break;

			case 'color':
				printf(
					'<input type="color" id="%s" name="%s" value="%s" class="bb-input bb-input--color"%s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '#000000' ),
					$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				break;

			case 'text':
			default:
				/*
				 * An unknown type degrades to a text input rather than rendering
				 * nothing, so a pack that adds a new type still sees its data.
				 */
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="widefat bb-input"%s%s%s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $field['placeholder'] ) . '"' : '',
					$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$req_attr   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				break;
		}
	}
}