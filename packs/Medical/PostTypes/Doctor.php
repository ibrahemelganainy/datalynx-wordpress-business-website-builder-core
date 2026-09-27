<?php

namespace BusinessBuilderCore\Packs\Medical\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Doctor (Medical domain entity).
 *
 * Owned by the Medical Pack. Minimal by design (Phase 19 §3): the proof needs a
 * name, a specialty, a photo, a short description and an optional profile URL —
 * nothing clinical and nothing patient-related.
 *
 * The admin UI is intentionally tiny: the post editor's own title/editor/excerpt/
 * thumbnail cover the visual fields, and one metabox adds the two values that have
 * no native equivalent (professional title + external profile URL). No custom
 * clinical fields, no scheduling, no records.
 */
class Doctor {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'bb_doctor';

	/**
	 * Meta keys owned by this entity.
	 */
	public const META_TITLE   = '_bb_doctor_title';
	public const META_PROFILE = '_bb_doctor_profile_url';
	public const META_ORDER   = '_bb_doctor_display_order';

	/**
	 * Nonce action / field.
	 */
	private const NONCE_ACTION = 'bb_save_doctor_fields';
	private const NONCE_FIELD  = 'bb_doctor_fields_nonce';

	/**
	 * Register hooks.
	 */
	public function register(): void {

		add_action( 'init', array( $this, 'register_post_type' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		add_action(
			'save_post_' . self::POST_TYPE,
			array( $this, 'save_meta' )
		);
	}

	/**
	 * Register the Doctor post type.
	 */
	public function register_post_type(): void {

		$labels = array(
			'name'               => __( 'Doctors', 'business-builder' ),
			'singular_name'      => __( 'Doctor', 'business-builder' ),
			'menu_name'          => __( 'Doctors', 'business-builder' ),
			'all_items'          => __( 'All Doctors', 'business-builder' ),
			'add_new'            => __( 'Add New', 'business-builder' ),
			'add_new_item'       => __( 'Add New Doctor', 'business-builder' ),
			'edit_item'          => __( 'Edit Doctor', 'business-builder' ),
			'new_item'           => __( 'New Doctor', 'business-builder' ),
			'view_item'          => __( 'View Doctor', 'business-builder' ),
			'search_items'       => __( 'Search Doctors', 'business-builder' ),
			'not_found'          => __( 'No doctors found.', 'business-builder' ),
			'not_found_in_trash' => __( 'No doctors found in Trash.', 'business-builder' ),
			'featured_image'     => __( 'Doctor Photo', 'business-builder' ),
			'set_featured_image' => __( 'Set Doctor Photo', 'business-builder' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'menu_icon'          => 'dashicons-heart',
				'publicly_queryable' => true,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => 'doctors' ),
				'supports'           => array(
					'title',
					'editor',
					'excerpt',
					'thumbnail',
					'page-attributes',
				),
				/*
				 * The pack owns its specialty taxonomy; it is attached here so the
				 * two post types can be filtered by it.
				 */
				'taxonomies'         => array( 'bb_specialty' ),
			)
		);
	}

	/**
	 * Register the Doctor metabox (professional title + optional profile URL).
	 */
	public function add_meta_box(): void {

		add_meta_box(
			'bb_doctor_details',
			__( 'Doctor Details', 'business-builder' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( $post ): void {

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$title   = (string) get_post_meta( $post->ID, self::META_TITLE, true );
		$profile = (string) get_post_meta( $post->ID, self::META_PROFILE, true );

		?>
		<p>
			<label for="bb_doctor_title"><strong><?php esc_html_e( 'Professional Title', 'business-builder' ); ?></strong></label>
			<input type="text" class="widefat" id="bb_doctor_title" name="bb_doctor_title"
				value="<?php echo esc_attr( $title ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. Consultant Cardiologist', 'business-builder' ); ?>">
		</p>
		<p>
			<label for="bb_doctor_profile_url"><strong><?php esc_html_e( 'External Profile URL', 'business-builder' ); ?></strong></label>
			<input type="url" class="widefat" id="bb_doctor_profile_url" name="bb_doctor_profile_url"
				value="<?php echo esc_attr( $profile ); ?>"
				placeholder="https://">
			<span class="description"><?php esc_html_e( 'Optional. Link to an external biography.', 'business-builder' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Persist the metabox values.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( $post_id ): void {

		$post_id = absint( $post_id );

		if ( $post_id <= 0 ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$title = isset( $_POST['bb_doctor_title'] )
			? sanitize_text_field( wp_unslash( $_POST['bb_doctor_title'] ) )
			: '';

		$profile = isset( $_POST['bb_doctor_profile_url'] )
			? esc_url_raw( wp_unslash( $_POST['bb_doctor_profile_url'] ) )
			: '';

		if ( '' === $title ) {
			delete_post_meta( $post_id, self::META_TITLE );
		} else {
			update_post_meta( $post_id, self::META_TITLE, $title );
		}

		if ( '' === $profile ) {
			delete_post_meta( $post_id, self::META_PROFILE );
		} else {
			update_post_meta( $post_id, self::META_PROFILE, $profile );
		}
	}

	/**
	 * Get the post type slug.
	 */
	public function get_post_type(): string {

		return self::POST_TYPE;
	}
}