<?php
/**
 * Component variant: Legal Service Card â€” featured (LawFirm domain).
 *
 * High-emphasis presentation of the SAME service. Same prepared args;
 * presentation only. Base class kept.
 *
 * @package BusinessBuilderCore\Packs\LawFirm
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
		'icon'       => '',
	)
);

$title      = (string) $args['title'];
$summary    = (string) $args['summary'];
$image_html = (string) $args['image_html'];
$icon       = (string) $args['icon'];

echo '<article class="bb-service-card bb-service-card--featured">';

if ( '' !== $image_html ) {
	echo '<div class="bb-service-image">';
	echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
} elseif ( '' !== $icon ) {
	echo '<div class="bb-service-icon">' . esc_html( $icon ) . '</div>';
}

echo '<div class="bb-service-body">';
echo '<h3>' . esc_html( $title ) . '</h3>';

if ( '' !== $summary ) {
	echo '<p>' . esc_html( $summary ) . '</p>';
}

echo '</div></article>';