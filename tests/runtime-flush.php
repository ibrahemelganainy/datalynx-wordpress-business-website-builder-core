<?php
/* Real WP runtime: prove the self-healing flush fixes lawyer permalinks. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

switch_to_blog( 2 );
do_action( 'init' );

$before_rules = get_option( 'rewrite_rules' );
$before_rules = is_array( $before_rules ) ? $before_rules : array();
$before_keys = array_keys( $before_rules );
$before_hit = preg_grep( '/bb_lawyer/', $before_keys );
echo 'BEFORE rules=' . count( $before_rules ) . ' bb_lawyer_hits=' . count( $before_hit ) . PHP_EOL;

flush_rewrite_rules( false );

$after_rules = get_option( 'rewrite_rules' );
$after_rules = is_array( $after_rules ) ? $after_rules : array();
$after_keys = array_keys( $after_rules );
$after_hit = preg_grep( '/bb_lawyer/', $after_keys );
echo 'AFTER rules=' . count( $after_rules ) . ' bb_lawyer_hits=' . count( $after_hit ) . PHP_EOL;

$q = new WP_Query( array(
    'post_type'      => 'bb_lawyer',
    'post_status'    => 'publish',
    'posts_per_page' => 3,
    'no_found_rows'  => true,
) );

foreach ( $q->posts as $p ) {
    echo 'lawyer ID=' . $p->ID . ' url=' . get_permalink( $p->ID ) . PHP_EOL;
}

restore_current_blog();
echo 'DONE' . PHP_EOL;
