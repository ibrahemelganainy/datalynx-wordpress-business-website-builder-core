<?php
/**
 * Component variant: Practice Area Card â€” minimal (LawFirm domain).
 *
 * Typography-first, no card chrome; icon inline before the title.
 * Same prepared args; presentation only.
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

echo '<article class="bb-practice-area-card bb-practice-area-card--minimal">';

echo '<h3>';
if ( '' !== $icon ) {
	echo '<span class="bb-practice-area-icon-inline" aria-hidden="true">' . esc_html( $icon ) . '</span> ';
}
echo esc_html( $title );
echo '</h3>';

if ( '' !== $summary ) {
	echo '<p>' . esc_html( $summary ) . '</p>';
}

echo '</article>';