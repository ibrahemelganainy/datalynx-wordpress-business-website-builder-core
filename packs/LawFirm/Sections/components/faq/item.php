<?php
/**
 * Component: FAQ Item (LawFirm domain).
 *
 * Presentation only; the caller prepares the data. Markup preserved verbatim â€”
 * a plain styled disclosure list (NOT an accordion), so no JS is introduced.
 *
 * Args:
 *   question string  Question text (raw; escaped here).
 *   answer   string  Answer HTML (rendered through wp_kses_post()).
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
		'question' => '',
		'answer'   => '',
	)
);

$question = (string) $args['question'];
$answer   = (string) $args['answer'];

echo '<div class="bb-faq-item">';
echo '<h3>' . esc_html( $question ) . '</h3>';
echo '<div class="bb-faq-answer">' . wp_kses_post( $answer ) . '</div>';
echo '</div>';