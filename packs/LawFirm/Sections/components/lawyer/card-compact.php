<?php
/**
 * Component variant: Lawyer Card â€” compact (LawFirm domain).
 *
 * A lower-density presentation of the SAME lawyer card. It consumes the EXACT
 * same prepared argument contract as lawyer/card.php (no new args, no queries,
 * no business logic) and keeps the same base class so the existing CSS/JS
 * contract is intact. The variant is selected via the component resolver
 * (lawyer/card + variant "compact" -> this file).
 *
 * Differences vs default (presentation only):
 *   - a `bb-lawyer-card--compact` modifier class,
 *   - the photo is not forced to a fixed aspect ratio (denser grid),
 *   - only the role line is always shown; the contact/social lines are kept
 *     (the domain card is identity + contact, so nothing is dropped).
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

echo '<article class="bb-lawyer-card bb-lawyer-card--compact">';

if ( $show_photo && '' !== $photo_html ) {
	echo '<div class="bb-lawyer-photo">';
	echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-lawyer-body">';

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