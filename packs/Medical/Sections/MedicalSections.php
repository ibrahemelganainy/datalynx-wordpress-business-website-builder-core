<?php

namespace BusinessBuilderCore\Packs\Medical\Sections;

use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Packs\Medical\Sections\MedicalQueries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Medical dynamic sections.
 *
 * Registers the pack's sections through the EXISTING core SectionRegistry and
 * renders them from the database. No MedicalSectionRegistry exists and none is
 * needed (Phase 19 §9).
 *
 * Dispatch uses the generic, type-agnostic `bb_render_section` filter introduced
 * in Phase 18: the CORE renderer never learns about Medical; this pack claims its
 * own types (Phase 19 §10).
 *
 * Presentation data is prepared ONCE per section and handed to the section
 * variant / component layer — variant templates never query (Phase 11 contract).
 */
class MedicalSections {

	/**
	 * Section types this pack owns.
	 */
	public const SECTION_DOCTORS          = 'doctors';
	public const SECTION_MEDICAL_SERVICES = 'medical_services';

	/**
	 * Card components this pack owns.
	 */
	public const COMPONENT_DOCTOR          = 'doctor/card';
	public const COMPONENT_MEDICAL_SERVICE = 'medical-service/card';

	/**
	 * Section category used for this pack's sections.
	 */
	public const CATEGORY = 'medical';

	protected SectionRegistry $registry;

	public function __construct( SectionRegistry $registry ) {

		$this->registry = $registry;
	}

	/**
	 * Register the pack's component template directory with the theme resolver.
	 *
	 * The theme owns generic presentation primitives; the pack owns its domain
	 * cards. Dependency direction stays one-way (Phase 10 §34 / Phase 18 §5).
	 *
	 * @param string[] $roots Existing component roots.
	 * @return string[]
	 */
	public function register_component_root( $roots ): array {

		$roots = is_array( $roots ) ? $roots : array();

		$roots[] = __DIR__ . '/components';

		return $roots;
	}

	/**
	 * Register the pack's SECTION LAYOUT VARIANTS.
	 *
	 * Variants are PRESENTATION strategies for a section (same data, same
	 * components). The pack owns its own domain variants, so core never learns
	 * about Medical (Phase 11 §22-§23).
	 *
	 * @param object $variants SectionVariants registry.
	 */
	public function register_section_variants( $variants ): void {

		if ( ! is_object( $variants ) || ! method_exists( $variants, 'register_many' ) ) {
			return;
		}

		$base = __DIR__ . '/variants';

		$variants->register_many(
			self::SECTION_DOCTORS,
			array(
				'default' => $base . '/doctors/default.php',
				'grid'    => $base . '/doctors/grid.php',
				'list'    => $base . '/doctors/list.php',
			)
		);

		$variants->register_many(
			self::SECTION_MEDICAL_SERVICES,
			array(
				'default'  => $base . '/medical-services/default.php',
				'featured' => $base . '/medical-services/featured.php',
			)
		);
	}

	/**
	 * Claim and render this pack's section types.
	 *
	 * Hooked on the generic `bb_render_section` filter (Phase 18). Returning true
	 * tells the core renderer the type was handled; anything else falls through to
	 * core's own generic content renderer.
	 *
	 * @param bool   $claimed  Whether a renderer already claimed the type.
	 * @param string $type     Section type slug.
	 * @param array  $section  Section data.
	 * @param array  $settings Section settings.
	 * @param array  $content  Section content.
	 * @return bool True when this pack rendered the section.
	 */
	public function render_section( $claimed, $type, $section = array(), $settings = array(), $content = array() ): bool {

		switch ( sanitize_key( (string) $type ) ) {

			case self::SECTION_DOCTORS:
				$this->render_doctors_section( $section, $settings, $content );
				return true;

			case self::SECTION_MEDICAL_SERVICES:
				$this->render_medical_services_section( $section, $settings, $content );
				return true;
		}

		return (bool) $claimed;
	}

	/**
	 * Register both Medical sections in the shared core registry.
	 */
	public function register(): void {

		$this->register_doctors();
		$this->register_medical_services();
	}

	/* ---------------------------------------------------------------------
	 * Shared schema helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Shared section heading fields (title + intro).
	 *
	 * @return array
	 */
	protected function heading_fields(): array {

		return array(
			'title'       => array(
				'type'        => 'text',
				'label'       => __( 'Section Title', 'business-builder' ),
				'default'     => '',
				'placeholder' => __( 'Enter a section title...', 'business-builder' ),
			),
			'description' => array(
				'type'    => 'textarea',
				'label'   => __( 'Intro Text', 'business-builder' ),
				'default' => '',
				'rows'    => 3,
			),
		);
	}

	/**
	 * Shared columns setting.
	 *
	 * @param int $default Default column count.
	 * @return array
	 */
	protected function columns_setting( int $default = 3 ): array {

		return array(
			'type'    => 'select',
			'label'   => __( 'Columns', 'business-builder' ),
			'default' => (string) $default,
			'options' => array(
				'1' => '1',
				'2' => '2',
				'3' => '3',
				'4' => '4',
			),
		);
	}

	/**
	 * Shared "limit" setting.
	 *
	 * @return array
	 */
	protected function limit_setting(): array {

		return array(
			'type'        => 'number',
			'label'       => __( 'Maximum Items', 'business-builder' ),
			'default'     => 0,
			'min'         => 0,
			'step'        => 1,
			'description' => __( '0 shows all items.', 'business-builder' ),
		);
	}

	/**
	 * Shared "featured only" setting.
	 *
	 * @return array
	 */
	protected function featured_setting(): array {

		return array(
			'type'        => 'checkbox',
			'label'       => __( 'Featured Only', 'business-builder' ),
			'default'     => false,
			'description' => __( 'Show only items marked as featured.', 'business-builder' ),
		);
	}

	/**
	 * Shared order setting.
	 *
	 * @return array
	 */
	protected function order_setting(): array {

		return array(
			'type'    => 'select',
			'label'   => __( 'Order', 'business-builder' ),
			'default' => 'asc',
			'options' => array(
				'asc'  => __( 'Ascending', 'business-builder' ),
				'desc' => __( 'Descending', 'business-builder' ),
			),
		);
	}

	/**
	 * Specialty filter setting, built from the pack's own taxonomy.
	 *
	 * @return array
	 */
	protected function specialty_setting(): array {

		$options = array(
			'' => __( 'All Specialties', 'business-builder' ),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => MedicalQueries::SPECIALTY_TAX,
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->slug ] = $term->name;
			}
		}

		return array(
			'type'    => 'select',
			'label'   => __( 'Specialty', 'business-builder' ),
			'default' => '',
			'options' => $options,
		);
	}

	/**
	 * The section "variant" (layout) setting.
	 *
	 * Returns an empty array when no variants are registered, so the builder never
	 * shows a pointless single-choice dropdown.
	 *
	 * @param string $section_type Section slug.
	 * @return array
	 */
	protected function variant_setting( string $section_type ): array {

		if ( ! function_exists( 'bb_section_variant_options' ) ) {
			return array();
		}

		$options = bb_section_variant_options( $section_type );

		if ( count( $options ) < 2 ) {
			return array();
		}

		return array(
			'type'    => 'select',
			'label'   => __( 'Layout', 'business-builder' ),
			'default' => 'default',
			'options' => $options,
		);
	}

	/**
	 * The "card_variant" (component design) setting for a component.
	 *
	 * ORTHOGONAL to the section "Layout" setting (Phase 12 §5). The option
	 * whitelist is the pack's own declaration of the card designs it ships, and
	 * every option is verified to resolve to a real template through the existing
	 * whitelist+traversal-safe bb_component_path() resolver.
	 *
	 * @param string                $component Component path (e.g. 'doctor/card').
	 * @param array<string, string> $variants  variant slug => translated label.
	 * @return array
	 */
	protected function card_variant_setting( string $component, array $variants ): array {

		$options = array();

		if ( function_exists( 'bb_component_path' ) ) {
			foreach ( $variants as $slug => $label ) {

				$slug = sanitize_key( (string) $slug );

				if ( '' === $slug ) {
					continue;
				}

				if ( 'default' === $slug || '' !== bb_component_path( $component, $slug ) ) {
					$options[ $slug ] = $label;
				}
			}
		} else {
			$options = $variants;
		}

		if ( count( $options ) < 2 ) {
			return array();
		}

		return array(
			'type'    => 'select',
			'label'   => __( 'Card design', 'business-builder' ),
			'default' => 'default',
			'options' => $options,
		);
	}

	/**
	 * The card-design catalog this pack ships (presentation metadata only).
	 *
	 * @return array<string, array<string, string>>
	 */
	public function card_variant_choices(): array {

		return array(
			self::COMPONENT_DOCTOR          => array(
				'default'    => __( 'Standard card', 'business-builder' ),
				'compact'    => __( 'Compact card', 'business-builder' ),
				'horizontal' => __( 'Horizontal card', 'business-builder' ),
			),
			self::COMPONENT_MEDICAL_SERVICE => array(
				'default'  => __( 'Standard card', 'business-builder' ),
				'featured' => __( 'Featured card', 'business-builder' ),
			),
		);
	}

	/**
	 * Build the card_variant setting for a component from the pack catalog.
	 *
	 * @param string $component Component path.
	 * @return array
	 */
	protected function card_setting_for( string $component ): array {

		$choices = $this->card_variant_choices();

		if ( ! isset( $choices[ $component ] ) ) {
			return array();
		}

		return $this->card_variant_setting( $component, $choices[ $component ] );
	}

	/* ---------------------------------------------------------------------
	 * Section registration
	 * ------------------------------------------------------------------ */

	/**
	 * Doctors section.
	 */
	protected function register_doctors(): void {

		$this->registry->register(
			self::SECTION_DOCTORS,
			array(
				'name'        => 'Doctors',
				'label'       => __( 'Doctors', 'business-builder' ),
				'description' => __( 'Display doctors from your database, optionally filtered by specialty.', 'business-builder' ),
				'category'    => self::CATEGORY,
				'icon'        => 'dashicons-heart',
				'supports'    => array( 'title', 'description', 'doctors', 'specialties' ),
				'settings'    => array_merge(
					array(
						'variant'      => $this->variant_setting( self::SECTION_DOCTORS ),
						'card_variant' => $this->card_setting_for( self::COMPONENT_DOCTOR ),
					),
					array(
						'columns'   => $this->columns_setting( 3 ),
						'limit'     => $this->limit_setting(),
						'featured'  => $this->featured_setting(),
						'specialty' => $this->specialty_setting(),
						'order'     => $this->order_setting(),
					)
				),
				'content'     => $this->heading_fields(),
			)
		);
	}

	/**
	 * Medical Services section.
	 */
	protected function register_medical_services(): void {

		$this->registry->register(
			self::SECTION_MEDICAL_SERVICES,
			array(
				'name'        => 'Medical Services',
				'label'       => __( 'Medical Services', 'business-builder' ),
				'description' => __( 'Display medical services from your database.', 'business-builder' ),
				'category'    => self::CATEGORY,
				'icon'        => 'dashicons-plus-alt',
				'supports'    => array( 'title', 'description', 'services', 'specialties' ),
				'settings'    => array_merge(
					array(
						'variant'      => $this->variant_setting( self::SECTION_MEDICAL_SERVICES ),
						'card_variant' => $this->card_setting_for( self::COMPONENT_MEDICAL_SERVICE ),
					),
					array(
						'columns'   => $this->columns_setting( 3 ),
						'limit'     => $this->limit_setting(),
						'featured'  => $this->featured_setting(),
						'specialty' => $this->specialty_setting(),
						'order'     => $this->order_setting(),
					)
				),
				'content'     => $this->heading_fields(),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Render section heading (title + intro) from prepared content.
	 *
	 * @param array $content Section content.
	 */
	protected function render_heading( array $content ): void {

		$title = (string) MedicalQueries::content( $content, 'title', '' );
		$intro = (string) MedicalQueries::content( $content, 'description', '' );

		if ( '' === $title && '' === $intro ) {
			return;
		}

		echo '<div class="bb-section-heading">';

		if ( '' !== $title ) {
			echo '<h2 class="bb-section-title">' . esc_html( $title ) . '</h2>';
		}

		if ( '' !== $intro ) {
			echo '<div class="bb-section-description">' . wp_kses_post( $intro ) . '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render the empty state.
	 *
	 * @param string $message Message.
	 */
	protected function render_empty( string $message ): void {

		echo '<div class="bb-section-inner"><div class="bb-medical-empty">'
			. esc_html( $message )
			. '</div></div>';
	}

	/**
	 * Render the Doctors section.
	 *
	 * @param array $section  Section data.
	 * @param array $settings Section settings.
	 * @param array $content  Section content.
	 */
	public function render_doctors_section( array $section, array $settings, array $content ): void {

		$columns = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 3;

		$queries = new MedicalQueries();

		$items = $queries->doctors( $settings );

		if ( empty( $items ) ) {
			$this->render_empty( __( 'No doctors found.', 'business-builder' ) );
			return;
		}

		/* Prepare the presentation data ONCE (Phase 11 §4-§5). */
		$card_args = array();

		foreach ( $items as $item ) {

			$photo_id = get_post_thumbnail_id( $item->ID );

			$photo_html = $photo_id
				? wp_get_attachment_image( $photo_id, 'medium', false, array( 'class' => 'bb-doctor-image' ) )
				: '';

			$specialty_names = array();

			$terms = get_the_terms( $item->ID, MedicalQueries::SPECIALTY_TAX );

			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$specialty_names[] = $term->name;
				}
			}

			$summary = ! empty( $item->post_excerpt )
				? $item->post_excerpt
				: wp_trim_words( wp_strip_all_tags( $item->post_content ), 18 );

			$profile_url = (string) get_post_meta(
				$item->ID,
				\BusinessBuilderCore\Packs\Medical\PostTypes\Doctor::META_PROFILE,
				true
			);

			$card_args[] = array(
				'name'         => (string) $item->post_title,
				'title'        => (string) get_post_meta(
					$item->ID,
					\BusinessBuilderCore\Packs\Medical\PostTypes\Doctor::META_TITLE,
					true
				),
				'specialty'    => implode( ', ', $specialty_names ),
				'summary'      => (string) $summary,
				'photo_html'   => (string) $photo_html,
				'profile_url'  => esc_url_raw( $profile_url ),
				'permalink'    => (string) get_permalink( $item->ID ),
				'is_public'    => 'publish' === $item->post_status,
				'show_photo'   => true,
			);
		}

		if ( empty( $card_args ) ) {
			$this->render_empty( __( 'No doctors found.', 'business-builder' ) );
			return;
		}

		echo '<div class="bb-section-inner">';

		$this->render_heading( $content );

		$variant      = isset( $settings['variant'] ) ? (string) $settings['variant'] : '';
		$card_variant = isset( $settings['card_variant'] ) ? (string) $settings['card_variant'] : '';

		$rendered = function_exists( 'bb_render_section_variant' )
			&& bb_render_section_variant(
				self::SECTION_DOCTORS,
				$variant,
				array(
					'items'             => $card_args,
					'columns'           => $columns,
					'settings'          => $settings,
					'content'           => $content,
					'component'         => self::COMPONENT_DOCTOR,
					'component_variant' => $card_variant,
					'type'              => self::SECTION_DOCTORS,
				)
			);

		/* Fall back to inline default markup when the variant layer is absent. */
		if ( ! $rendered ) {

			echo '<div class="bb-doctors-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

			foreach ( $card_args as $one ) {
				bb_component( self::COMPONENT_DOCTOR, $one, $card_variant );
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render the Medical Services section.
	 *
	 * @param array $section  Section data.
	 * @param array $settings Section settings.
	 * @param array $content  Section content.
	 */
	public function render_medical_services_section( array $section, array $settings, array $content ): void {

		$columns = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 3;

		$queries = new MedicalQueries();

		$items = $queries->medical_services( $settings );

		if ( empty( $items ) ) {
			$this->render_empty( __( 'No medical services found.', 'business-builder' ) );
			return;
		}

		$card_args = array();

		foreach ( $items as $item ) {

			$image_id = get_post_thumbnail_id( $item->ID );

			$image_html = $image_id
				? wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bb-medical-service-thumb' ) )
				: '';

			$summary = ! empty( $item->post_excerpt )
				? $item->post_excerpt
				: wp_trim_words( wp_strip_all_tags( $item->post_content ), 18 );

			$url = (string) get_post_meta(
				$item->ID,
				\BusinessBuilderCore\Packs\Medical\PostTypes\MedicalService::META_URL,
				true
			);

			$link      = '' !== $url ? esc_url_raw( $url ) : (string) get_permalink( $item->ID );
			$is_public = 'publish' === $item->post_status;

			$card_args[] = array(
				'title'     => (string) $item->post_title,
				'summary'   => (string) $summary,
				'image_html' => (string) $image_html,
				'url'       => $link,
				'is_public' => $is_public,
			);
		}

		if ( empty( $card_args ) ) {
			$this->render_empty( __( 'No medical services found.', 'business-builder' ) );
			return;
		}

		echo '<div class="bb-section-inner">';

		$this->render_heading( $content );

		$variant      = isset( $settings['variant'] ) ? (string) $settings['variant'] : '';
		$card_variant = isset( $settings['card_variant'] ) ? (string) $settings['card_variant'] : '';

		$rendered = function_exists( 'bb_render_section_variant' )
			&& bb_render_section_variant(
				self::SECTION_MEDICAL_SERVICES,
				$variant,
				array(
					'items'             => $card_args,
					'columns'           => $columns,
					'settings'          => $settings,
					'content'           => $content,
					'component'         => self::COMPONENT_MEDICAL_SERVICE,
					'component_variant' => $card_variant,
					'type'              => self::SECTION_MEDICAL_SERVICES,
				)
			);

		if ( ! $rendered ) {

			echo '<div class="bb-medical-services-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

			foreach ( $card_args as $one ) {
				bb_component( self::COMPONENT_MEDICAL_SERVICE, $one, $card_variant );
			}

			echo '</div>';
		}

		echo '</div>';
	}
}