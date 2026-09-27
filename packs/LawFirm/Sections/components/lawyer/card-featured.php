<?php
/**
 * Component variant: Lawyer Card â€” featured (LawFirm domain).
 *
 * High-emphasis presentation of the SAME lawyer card. Same prepared args as
 * lawyer/card.php; presentation only (no queries, no business logic). Base
 * class `bb-lawyer-card` is kept so CSS/JS contracts are intact.
 *
 * Composition difference vs default:
 *   - a `bb-lawyer-card--featured` modifier,
 *   - a dedicated `bb-lawyer-featured-name` heading emphasis,
 *   - a primary action link (contact/phone) surfaced when available.
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

echo '<article class="bb-lawyer-card bb-lawyer-card--featured">';

if ( $show_photo && '' !== $photo_html ) {
	echo '<div class="bb-lawyer-photo">';
	echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

echo '<div class="bb-lawyer-body">';

if ( '' !== $profile_url && $is_public ) {
	echo '<h3 class="bb-lawyer-featured-name"><a class="bb-lawyer-link" href="' . esc_url( $profile_url ) . '">' . esc_html( $name ) . '</a></h3>';
} else {
	echo '<h3 class="bb-lawyer-featured-name">' . esc_html( $name ) . '</h3>';
}

if ( '' !== $role ) {
	echo '<p class="bb-lawyer-role">' . esc_html( $role ) . '</p>';
}

if ( '' !== $experience ) {
	echo '<p class="bb-lawyer-meta">' . esc_html( sprintf( /* translators: %s: years of experience. */ __( '%s years experience', 'business-builder' ), $experience ) ) . '</p>';
}

/* Featured surfaces a single, prominent primary contact action. */
$primary_url   = '';
$primary_label = '';

if ( '' !== $phone ) {
	$primary_url   = 'tel:' . $phone;
	$primary_label = $phone;
} elseif ( '' !== $email ) {
	$primary_url   = 'mailto:' . $email;
	$primary_label = $email;
} elseif ( '' !== $profile_link ) {
	$primary_url   = $profile_link;
	$primary_label = __( 'Profile', 'business-builder' );
}

if ( '' !== $primary_url ) {
	echo '<p class="bb-lawyer-action"><a class="bb-button bb-button-primary" href="' . esc_url( $primary_url ) . '">' . esc_html( $primary_label ) . '</a></p>';
}

echo '</div></article>';