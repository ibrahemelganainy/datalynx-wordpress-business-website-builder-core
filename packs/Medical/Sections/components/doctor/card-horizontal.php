<?php
/**
 * Component: Doctor Card — horizontal variant (Medical domain).
 *
 * Directory row: photo beside the details. Pairs naturally with the section
 * "list" layout. Same prepared args contract — presentation only, no queries,
 * Theme tokens only.
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
		'summary'     => '',
		'photo_html'  => '',
		'profile_url' => '',
		'permalink'   => '',
		'is_public'   => false,
		'show_photo'  => true,
	)
);

$name        = (string) $args['name'];
$title       = (string) $args['title'];
$specialty   = (string) $args['specialty'];
$summary     = (string) $args['summary'];
$photo_html  = (string) $args['photo_html'];
$profile_url = (string) $args['profile_url'];
$permalink   = (string) $args['permalink'];
$is_public   = (bool) $args['is_public'];
$show_photo  = (bool) $args['show_photo'];

echo '<article class="bb-doctor-card bb-doctor-card-horizontal">';

if ( $show_photo && '' !== $photo_html ) {
	echo '<div class="bb-doctor-photo bb-doctor-photo-inline">';
	echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-doctor-body">';

if ( $is_public && '' !== $permalink ) {
	echo '<h3><a class="bb-doctor-link" href="' . esc_url( $permalink ) . '">' . esc_html( $name ) . '</a></h3>';
} else {
	echo '<h3>' . esc_html( $name ) . '</h3>';
}

if ( '' !== $title ) {
	echo '<p class="bb-doctor-title">' . esc_html( $title ) . '</p>';
}

if ( '' !== $specialty ) {
	echo '<p class="bb-doctor-specialty">' . esc_html( $specialty ) . '</p>';
}

if ( '' !== $summary ) {
	echo '<p class="bb-doctor-summary">' . esc_html( $summary ) . '</p>';
}

if ( '' !== $profile_url ) {
	echo '<p><a class="bb-doctor-profile-link" href="' . esc_url( $profile_url ) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Profile', 'business-builder' )
		. '</a></p>';
}

echo '</div></article>';