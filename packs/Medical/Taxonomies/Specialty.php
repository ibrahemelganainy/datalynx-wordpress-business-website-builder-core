<?php

namespace BusinessBuilderCore\Packs\Medical\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Specialty taxonomy (Medical domain).
 *
 * Owned by the Medical Pack and attached to both pack post types. It exists to
 * prove that a pack can own a taxonomy and filter its own sections by it — it is
 * deliberately *not* a departments/hospital-structure system (Phase 19 §3).
 *
 * Term meta: `_bb_specialty_icon` (string, optional dashicon/emoji).
 */
class Specialty {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'bb_specialty';

	/**
	 * Term meta key owned by this taxonomy.
	 */
	public const META_ICON = '_bb_specialty_icon';

	/**
	 * Register hooks.
	 */
	public function register(): void {

		add_action( 'init', array( $this, 'register_taxonomy' ) );

		add_action(
			'init',
			array( $this, 'register_term_meta' )
		);
	}

	/**
	 * Register the taxonomy.
	 */
	public function register_taxonomy(): void {

		$labels = array(
			'name'          => __( 'Specialties', 'business-builder' ),
			'singular_name' => __( 'Specialty', 'business-builder' ),
			'menu_name'     => __( 'Specialties', 'business-builder' ),
			'all_items'     => __( 'All Specialties', 'business-builder' ),
			'add_new_item'  => __( 'Add New Specialty', 'business-builder' ),
			'edit_item'     => __( 'Edit Specialty', 'business-builder' ),
			'search_items'  => __( 'Search Specialties', 'business-builder' ),
			'not_found'     => __( 'No specialties found.', 'business-builder' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( 'bb_doctor', 'bb_medical_service' ),
			array(
				'labels'            => $labels,
				'public'            => true,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'specialty' ),
			)
		);
	}

	/**
	 * Register the term-meta field so it is available over REST / register_meta.
	 */
	public function register_term_meta(): void {

		register_term_meta(
			self::TAXONOMY,
			self::META_ICON,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'manage_categories' );
				},
			)
		);

		/*
		 * Minimal term-meta UI on the standard taxonomy screens, mirroring the
		 * tiny field surface the pack post types use.
		 */
		add_action(
			self::TAXONOMY . '_add_form_fields',
			array( $this, 'render_add_field' )
		);

		add_action(
			self::TAXONOMY . '_edit_form_fields',
			array( $this, 'render_edit_field' )
		);

		add_action(
			'created_' . self::TAXONOMY,
			array( $this, 'save_term_meta' )
		);

		add_action(
			'edited_' . self::TAXONOMY,
			array( $this, 'save_term_meta' )
		);
	}

	/**
	 * Render the field on the "add term" form.
	 */
	public function render_add_field(): void {

		?>
		<div class="form-field">
			<label for="bb_specialty_icon"><?php esc_html_e( 'Icon', 'business-builder' ); ?></label>
			<input type="text" name="bb_specialty_icon" id="bb_specialty_icon" value="">
			<p class="description"><?php esc_html_e( 'Optional icon (e.g. a dashicon class).', 'business-builder' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the field on the "edit term" form.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public function render_edit_field( $term ): void {

		$icon = (string) get_term_meta( $term->term_id, self::META_ICON, true );

		?>
		<tr class="form-field">
			<th scope="row">
				<label for="bb_specialty_icon"><?php esc_html_e( 'Icon', 'business-builder' ); ?></label>
			</th>
			<td>
				<input type="text" name="bb_specialty_icon" id="bb_specialty_icon"
					value="<?php echo esc_attr( $icon ); ?>">
				<p class="description"><?php esc_html_e( 'Optional icon (e.g. a dashicon class).', 'business-builder' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist the term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term_meta( $term_id ): void {

		$term_id = absint( $term_id );

		if ( $term_id <= 0 ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( ! isset( $_POST['bb_specialty_icon'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verifies the taxonomy nonce.
			return;
		}

		$icon = sanitize_text_field( wp_unslash( $_POST['bb_specialty_icon'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $icon ) {
			delete_term_meta( $term_id, self::META_ICON );
			return;
		}

		update_term_meta( $term_id, self::META_ICON, $icon );
	}
}