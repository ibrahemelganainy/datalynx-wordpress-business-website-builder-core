<?php
/**
 * Component: Medical Service Card — default (Medical domain).
 *
 * Presentation only. Every value is PREPARED by the caller
 * (MedicalSections::render_medical_services_section) — no query, no business
 * logic, no Page Builder awareness. Consumes Theme tokens only.
 *
 * Args:
 *   title      string Service title.
 *   summary    string Short description.
 *   image_html string Pre-rendered <img>, or ''.
 *   url        string Destination URL (may be '').
 *   is_public  bool   Whether links should be used.
 *
 * @package BusinessBuilderCore\Packs\Medical
 * @var array $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = wp_parse_args(
	$args,
	array(
		'title'      => '',
		'summary'    => '',
		'image_html' => '',
		'url'        => '',
		'is_public'  => false,
	)
);

$title      = (string) $args['title'];
$summary    = (string) $args['summary'];
$image_html = (string) $args['image_html'];
$url        = (string) $args['url'];
$is_public  = (bool) $args['is_public'];

echo '<article class="bb-medical-service-card">';

if ( '' !== $image_html ) {
	echo '<div class="bb-medical-service-media">';
	echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-medical-service-body">';

if ( $is_public && '' !== $url ) {
	echo '<h3><a class="bb-medical-service-link" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h3>';
} else {
	echo '<h3>' . esc_html( $title ) . '</h3>';
}

if ( '' !== $summary ) {
	echo '<p class="bb-medical-service-summary">' . esc_html( $summary ) . '</p>';
}

echo '</div></article>';