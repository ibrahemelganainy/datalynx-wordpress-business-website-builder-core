<?php
/* Real WP runtime test: practice-area resolution for blog 2. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/packs/LawFirm/PostTypes/ConsultationMeta.php';

switch_to_blog( 2 );
do_action( 'init' );

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;

echo 'taxonomy exists: ' . var_export( taxonomy_exists( 'bb_practice_area' ), true ) . PHP_EOL;

$terms = get_terms( array( 'taxonomy' => 'bb_practice_area', 'hide_empty' => false ) );

if ( is_wp_error( $terms ) ) {
    echo 'TERMS ERROR: ' . $terms->get_error_message() . PHP_EOL;
} else {
    echo 'TERMS: ' . count( $terms ) . PHP_EOL;
    foreach ( $terms as $t ) {
        echo 'term id=' . $t->term_id . ' name=' . $t->name . PHP_EOL;
    }
}

$stored = get_post_meta( 63, '_bb_consultation_practice_area', true );
echo 'STORED hex: ' . bin2hex( (string) $stored ) . PHP_EOL;

$resolved = ConsultationMeta::resolve_practice_area( $stored );
$name = ( $resolved instanceof WP_Term ) ? $resolved->name : '(null)';
echo 'RESOLVED: ' . $name . PHP_EOL;

restore_current_blog();
echo 'DONE' . PHP_EOL;
