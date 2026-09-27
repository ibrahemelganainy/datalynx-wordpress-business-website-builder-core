<?php

namespace BusinessBuilderCore\Packs\Medical\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Medical Service (Medical domain entity).
 *
 * Owned by the Medical Pack. Minimal by design (Phase 19 §3): title, summary,
 * image/icon and an optional URL. It is a *marketing* service entry — not a
 * billable clinical procedure, not insurance-linked, not a record.
 *
 * Native post fields carry the visual proof (title / excerpt / content /
 * thumbnail); the single metabox adds the optional external URL.
 */
class MedicalService {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'bb_medical_service';

	/**
	 * Meta keys owned by this entity.
	 */
	public const META_URL   = '_bb_medical_service_url';
	public const META_ORDER = '_bb_medical_service_display_order';

	/**
	 * Nonce action / field.
	 */
	private const NONCE_ACTION = 'bb_save_medical_service_fields';
	private const NONCE_FIELD  = 'bb_medical_service_fields_nonce';

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
	 * Register the Medical Service post type.
	 */
	public function register_post_type(): void {

		$labels = array(
			'name'               => __( 'Medical Services', 'business-builder' ),
			'singular_name'      => __( 'Medical Service', 'business-builder' ),
			'menu_name'          => __( 'Medical Services', 'business-builder' ),
			'all_items'          => __( 'All Medical Services', 'business-builder' ),
			'add_new'            => __( 'Add New', 'business-builder' ),
			'add_new_item'       => __( 'Add New Medical Service', 'business-builder' ),
			'edit_item'          => __( 'Edit Medical Service', 'business-builder' ),
			'new_item'           => __( 'New Medical Service', 'business-builder' ),
			'view_item'          => __( 'View Medical Service', 'business-builder' ),
			'search_items'       => __( 'Search Medical Services', 'business-builder' ),
			'not_found'          => __( 'No medical services found.', 'business-builder' ),
			'not_found_in_trash' => __( 'No medical services found in Trash.', 'business-builder' ),
			'featured_image'     => __( 'Service Image', 'business-builder' ),
			'set_featured_image' => __( 'Set Service Image', 'business-builder' ),
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
				'menu_icon'          => 'dashicons-plus-alt',
				'publicly_queryable' => true,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => 'medical-services' ),
				'supports'           => array(
					'title',
					'editor',
					'excerpt',
					'thumbnail',
					'page-attributes',
				),
				'taxonomies'         => array( 'bb_specialty' ),
			)
		);
	}

	/**
	 * Register the metabox (optional external URL).
	 */
	public function add_meta_box(): void {

		add_meta_box(
			'bb_medical_service_details',
			__( 'Service Details', 'business-builder' ),
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

		$url = (string) get_post_meta( $post->ID, self::META_URL, true );

		?>
		<p>
			<label for="bb_medical_service_url"><strong><?php esc_html_e( 'Service URL', 'business-builder' ); ?></strong></label>
			<input type="url" class="widefat" id="bb_medical_service_url" name="bb_medical_service_url"
				value="<?php echo esc_attr( $url ); ?>"
				placeholder="https://">
			<span class="description"><?php esc_html_e( 'Optional. Where the service card should link.', 'business-builder' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Persist the metabox value.
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

		$url = isset( $_POST['bb_medical_service_url'] )
			? esc_url_raw( wp_unslash( $_POST['bb_medical_service_url'] ) )
			: '';

		if ( '' === $url ) {
			delete_post_meta( $post_id, self::META_URL );
		} else {
			update_post_meta( $post_id, self::META_URL, $url );
		}
	}

	/**
	 * Get the post type slug.
	 */
	public function get_post_type(): string {

		return self::POST_TYPE;
	}
}