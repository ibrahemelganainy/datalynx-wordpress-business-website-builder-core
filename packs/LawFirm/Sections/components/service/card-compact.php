<?php
/**
 * Component variant: Legal Service Card â€” compact (LawFirm domain).
 *
 * Same prepared argument contract as service/card.php; presentation-only.
 * Adds the `bb-service-card--compact` modifier; the compact CSS arranges the
 * icon/image inline with the text instead of stacked.
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

echo '<article class="bb-service-card bb-service-card--compact">';

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