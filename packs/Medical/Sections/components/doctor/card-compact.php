<?php
/**
 * Component: Doctor Card — compact variant (Medical domain).
 *
 * Dense variant for large grids: smaller media treatment and no free-text
 * summary. Same prepared args contract as doctor/card.php — presentation only,
 * no queries, Theme tokens only.
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
		'name'        => '',
		'title'       => '',
		'specialty'   => '',
		'photo_html'  => '',
		'permalink'   => '',
		'is_public'   => false,
		'show_photo'  => true,
	)
);

$name       = (string) $args['name'];
$title      = (string) $args['title'];
$specialty  = (string) $args['specialty'];
$photo_html = (string) $args['photo_html'];
$permalink  = (string) $args['permalink'];
$is_public  = (bool) $args['is_public'];
$show_photo = (bool) $args['show_photo'];

echo '<article class="bb-doctor-card bb-doctor-card-compact">';

if ( $show_photo && '' !== $photo_html ) {
	echo '<div class="bb-doctor-photo bb-doctor-photo-compact">';
	echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-doctor-body">';

if ( $is_public && '' !== $permalink ) {
	echo '<h4><a class="bb-doctor-link" href="' . esc_url( $permalink ) . '">' . esc_html( $name ) . '</a></h4>';
} else {
	echo '<h4>' . esc_html( $name ) . '</h4>';
}

if ( '' !== $title ) {
	echo '<p class="bb-doctor-title">' . esc_html( $title ) . '</p>';
}

if ( '' !== $specialty ) {
	echo '<p class="bb-doctor-specialty">' . esc_html( $specialty ) . '</p>';
}

echo '</div></article>';