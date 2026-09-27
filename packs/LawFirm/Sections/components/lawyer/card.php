<?php
/**
 * Component: Lawyer Card (LawFirm domain).
 *
 * Presentation only. Every value is PREPARED by the caller
 * (LawFirmSections::render_lawyers_section) â€” this template performs no query
 * and no business logic. Markup, classes and semantics are preserved verbatim
 * from the original inline renderer so the existing CSS/JS contract is intact.
 *
 * Args:
 *   name          string  Lawyer display name (raw; escaped here).
 *   profile_url   string  Canonical profile URL, or '' when not public.
 *   is_public     bool    Whether the profile link should be used.
 *   photo_html    string  Pre-rendered <img> for the photo, or ''.
 *   show_photo    bool    Whether a photo may be shown.
 *   role          string  Professional title (raw).
 *   experience    string  Years of experience (raw; formatted here).
 *   phone         string  Phone number (raw).
 *   email         string  Email address (raw).
 *   profile_link  string  External social URL, or ''.
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
		'name'         => '',
		'profile_url'  => '',
		'is_public'    => false,
		'photo_html'   => '',
		'show_photo'   => true,
		'role'         => '',
		'experience'   => '',
		'phone'        => '',
		'email'        => '',
		'profile_link' => '',
	)
);

$name        = (string) $args['name'];
$profile_url = (string) $args['profile_url'];
$is_public   = (bool) $args['is_public'];
$photo_html  = (string) $args['photo_html'];
$show_photo  = (bool) $args['show_photo'];
$role        = (string) $args['role'];
$experience  = (string) $args['experience'];
$phone       = (string) $args['phone'];
$email       = (string) $args['email'];
$profile_link = (string) $args['profile_link'];

echo '<article class="bb-lawyer-card">';

if ( $show_photo && '' !== $photo_html ) {
	echo '<div class="bb-lawyer-photo">';
	echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-lawyer-body">';

/*
 * Link the name to the canonical profile URL. Only published lawyers have a
 * working single page, so drafts render the name as plain text instead of a
 * dead link.
 */
if ( '' !== $profile_url && $is_public ) {
	echo '<h3><a class="bb-lawyer-link" href="' . esc_url( $profile_url ) . '">' . esc_html( $name ) . '</a></h3>';
} else {
	echo '<h3>' . esc_html( $name ) . '</h3>';
}

if ( '' !== $role ) {
	echo '<p class="bb-lawyer-role">' . esc_html( $role ) . '</p>';
}

if ( '' !== $experience ) {
	echo '<p class="bb-lawyer-meta">' . esc_html( sprintf( /* translators: %s: years of experience. */ __( '%s years experience', 'business-builder' ), $experience ) ) . '</p>';
}

if ( '' !== $phone ) {
	echo '<p><a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a></p>';
}

if ( '' !== $email ) {
	echo '<p><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
}

if ( '' !== $profile_link ) {
	echo '<p><a href="' . esc_url( $profile_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Profile', 'business-builder' ) . '</a></p>';
}

echo '</div></article>';