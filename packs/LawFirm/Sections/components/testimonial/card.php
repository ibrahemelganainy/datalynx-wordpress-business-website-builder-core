<?php
/**
 * Component: Testimonial Card (LawFirm domain).
 *
 * Presentation only; the caller prepares the data. Markup preserved verbatim
 * (image, rating stars, blockquote, author name, author title).
 *
 * Args:
 *   author       string  Display name (raw; escaped here).
 *   author_title string  Role/title (raw; escaped here).
 *   quote        string  Quote text (raw; trimmed + escaped here).
 *   rating       int     0..5 star rating (0 = no rating row).
 *   image_html   string  Pre-rendered <img> (class bb-testimonial-thumb), or ''.
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
		'author'       => '',
		'author_title' => '',
		'quote'        => '',
		'rating'       => 0,
		'image_html'   => '',
	)
);

$author       = (string) $args['author'];
$author_title = (string) $args['author_title'];
$quote        = (string) $args['quote'];
$rating       = absint( $args['rating'] );
$image_html   = (string) $args['image_html'];

echo '<article class="bb-testimonial-card">';

if ( '' !== $image_html ) {
	echo '<div class="bb-testimonial-image">';
	echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	echo '</div>';
}

if ( $rating > 0 ) {
	$stars = min( 5, $rating );

	/*
	 * The star glyph is built from its code point (U+2605) so the source stays
	 * pure ASCII. An aria-label conveys the rating to assistive tech, since a
	 * run of star characters is not meaningful when read aloud.
	 */
	$rating_label = sprintf(
		/* translators: %d: star rating out of five. */
		__( '%d out of 5', 'business-builder' ),
		$stars
	);

	echo '<div class="bb-testimonial-rating" aria-label="' . esc_attr( $rating_label ) . '">' . str_repeat( "\u{2605}", $stars ) . '</div>';
}

if ( '' !== $quote ) {
	echo '<blockquote>' . esc_html( wp_trim_words( $quote, 30 ) ) . '</blockquote>';
}

echo '<h3>' . esc_html( $author ) . '</h3>';

if ( '' !== $author_title ) {
	echo '<p>' . esc_html( $author_title ) . '</p>';
}

echo '</article>';