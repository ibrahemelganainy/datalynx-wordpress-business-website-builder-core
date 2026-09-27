<?php
/**
 * Component: Medical Service Card — featured variant (Medical domain).
 *
 * High-emphasis treatment: larger media, and the summary is presented more
 * prominently. Same prepared args contract as medical-service/card.php —
 * presentation only, no queries, Theme tokens only.
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

echo '<article class="bb-medical-service-card bb-medical-service-card-featured">';

if ( '' !== $image_html ) {
	echo '<div class="bb-medical-service-media bb-medical-service-media-large">';
	echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-medical-service-body">';

echo '<span class="bb-medical-service-eyebrow">' . esc_html__( 'Featured', 'business-builder' ) . '</span>';

if ( $is_public && '' !== $url ) {
	echo '<h3><a class="bb-medical-service-link" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h3>';
} else {
	echo '<h3>' . esc_html( $title ) . '</h3>';
}

if ( '' !== $summary ) {
	echo '<p class="bb-medical-service-summary">' . esc_html( $summary ) . '</p>';
}

echo '</div></article>';