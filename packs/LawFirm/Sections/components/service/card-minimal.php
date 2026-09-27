<?php
/**
 * Component variant: Legal Service Card â€” minimal (LawFirm domain).
 *
 * Typography-first, no card chrome. Same prepared args; presentation only.
 * The icon (when present) is rendered inline before the title.
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

$title   = (string) $args['title'];
$summary = (string) $args['summary'];
$icon    = (string) $args['icon'];

echo '<article class="bb-service-card bb-service-card--minimal">';

echo '<h3>';
if ( '' !== $icon ) {
	echo '<span class="bb-service-icon-inline" aria-hidden="true">' . esc_html( $icon ) . '</span> ';
}
echo esc_html( $title );
echo '</h3>';

if ( '' !== $summary ) {
	echo '<p>' . esc_html( $summary ) . '</p>';
}

echo '</article>';