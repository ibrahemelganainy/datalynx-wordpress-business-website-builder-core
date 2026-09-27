<?php
/**
 * Component: Legal Service Card (LawFirm domain).
 *
 * Presentation only; the caller prepares the data. Markup preserved verbatim.
 *
 * Args:
 *   title      string  Service title (raw; escaped here).
 *   summary    string  Plain-text summary (raw; escaped here).
 *   image_html string  Pre-rendered <img> (class bb-service-thumb), or ''.
 *   icon       string  Icon identifier used only when there is no image.
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

echo '<article class="bb-service-card">';

if ( '' !== $image_html ) {
	echo '<div class="bb-service-image">';
	echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
} elseif ( '' !== $icon ) {
	echo '<div class="bb-service-icon">' . esc_html( $icon ) . '</div>';
}

echo '<h3>' . esc_html( $title ) . '</h3>';

if ( '' !== $summary ) {
	echo '<p>' . esc_html( $summary ) . '</p>';
}

echo '</article>';