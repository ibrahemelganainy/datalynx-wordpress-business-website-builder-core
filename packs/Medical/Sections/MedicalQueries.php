<?php

namespace BusinessBuilderCore\Packs\Medical\Sections;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reusable query logic for the Medical dynamic sections.
 *
 * Mirrors the LawFirm Pack's LawFirmQueries role (audit §12): ALL database /
 * business querying for this pack lives here, so section render methods stay
 * presentation-only and variant templates never query (Phase 11 contract).
 *
 * Every method honours the shared section settings: limit, featured-only, order
 * and (where the entity supports it) a specialty filter.
 */
class MedicalQueries {

	/**
	 * Post types (owned by this pack).
	 */
	public const DOCTOR          = 'bb_doctor';
	public const MEDICAL_SERVICE = 'bb_medical_service';

	/**
	 * Specialty taxonomy (owned by this pack).
	 */
	public const SPECIALTY_TAX = 'bb_specialty';

	/**
	 * Build common query args shared by both entities.
	 *
	 * @param string $post_type  Post type slug.
	 * @param array  $settings   Section settings.
	 * @param string $order_meta Display-order meta key.
	 * @return array
	 */
	private function base_args(
		string $post_type,
		array $settings,
		string $order_meta
	): array {

		$limit = isset( $settings['limit'] )
			? absint( $settings['limit'] )
			: 0;

		$direction = ( ! empty( $settings['order'] ) && 'desc' === $settings['order'] )
			? 'DESC'
			: 'ASC';

		/*
		 * Order by the display-order meta through a NAMED clause rather than a
		 * bare meta_key, so records that have never been given an order are kept
		 * (ordered last) instead of being silently dropped by an INNER JOIN.
		 */
		return array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'no_found_rows'  => true,
			'orderby'        => array(
				'order_clause' => $direction,
				'date'         => 'DESC',
			),
			'meta_query'     => array(
				'relation'     => 'AND',
				'order_clause' => array(
					'relation' => 'OR',
					array(
						'key'     => $order_meta,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => $order_meta,
						'compare' => 'NOT EXISTS',
					),
				),
			),
		);
	}

	/**
	 * Apply a "featured only" meta filter when requested.
	 *
	 * @param array  $args     Query args (by reference).
	 * @param array  $settings Section settings.
	 * @param string $meta_key Featured meta key.
	 */
	private function apply_featured( array &$args, array $settings, string $meta_key ): void {

		if ( empty( $settings['featured'] ) ) {
			return;
		}

		$args['meta_query']['featured_clause'] = array(
			'key'     => $meta_key,
			'value'   => '1',
			'compare' => '=',
		);
	}

	/**
	 * Apply a specialty term filter when requested.
	 *
	 * @param array  $args     Query args (by reference).
	 * @param array  $settings Section settings.
	 */
	private function apply_specialty( array &$args, array $settings ): void {

		if ( empty( $settings['specialty'] ) ) {
			return;
		}

		$term = sanitize_title( (string) $settings['specialty'] );

		if ( '' === $term ) {
			return;
		}

		$args['tax_query'] = array(
			array(
				'taxonomy' => self::SPECIALTY_TAX,
				'field'    => 'slug',
				'terms'    => $term,
			),
		);
	}

	/**
	 * Query doctors.
	 *
	 * @param array $settings Section settings.
	 * @return \WP_Post[]
	 */
	public function doctors( array $settings ): array {

		$args = $this->base_args(
			self::DOCTOR,
			$settings,
			\BusinessBuilderCore\Packs\Medical\PostTypes\Doctor::META_ORDER
		);

		$this->apply_featured(
			$args,
			$settings,
			'_bb_doctor_featured'
		);

		$this->apply_specialty( $args, $settings );

		return get_posts( $args );
	}

	/**
	 * Query medical services.
	 *
	 * @param array $settings Section settings.
	 * @return \WP_Post[]
	 */
	public function medical_services( array $settings ): array {

		$args = $this->base_args(
			self::MEDICAL_SERVICE,
			$settings,
			\BusinessBuilderCore\Packs\Medical\PostTypes\MedicalService::META_ORDER
		);

		$this->apply_featured(
			$args,
			$settings,
			'_bb_medical_service_featured'
		);

		$this->apply_specialty( $args, $settings );

		return get_posts( $args );
	}

	/**
	 * Get a section's content value with a fallback.
	 *
	 * @param array  $content Section content.
	 * @param string $key     Content key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function content( array $content, string $key, $default = '' ) {

		return isset( $content[ $key ] ) && '' !== $content[ $key ]
			? $content[ $key ]
			: $default;
	}
}