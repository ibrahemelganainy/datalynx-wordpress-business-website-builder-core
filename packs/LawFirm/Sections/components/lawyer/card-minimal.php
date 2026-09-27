<?php
/**
 * Component variant: Lawyer Card â€” minimal (LawFirm domain).
 *
 * Low-decoration, typography-first presentation of the SAME data. Same prepared
 * args; presentation only. Base class kept.
 *
 * Composition difference vs default:
 *   - a `bb-lawyer-card--minimal` modifier (no photo panel, no card chrome),
 *   - name + role + the single most relevant contact line only.
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
$role        = (string) $args['role'];
$experience  = (string) $args['experience'];
$phone       = (string) $args['phone'];
$email       = (string) $args['email'];

echo '<article class="bb-lawyer-card bb-lawyer-card--minimal">';
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

/* Minimal keeps exactly one contact line. */
if ( '' !== $phone ) {
	echo '<p><a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a></p>';
} elseif ( '' !== $email ) {
	echo '<p><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
}

echo '</div></article>';