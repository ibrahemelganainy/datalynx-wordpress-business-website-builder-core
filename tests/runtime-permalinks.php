<?php
/* Real WP runtime: what permalink does WP generate for a published lawyer? */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

switch_to_blog( 2 );
do_action( 'init' );

$q = new WP_Query( array(
    'post_type'      => 'bb_lawyer',
    'post_status'    => 'publish',
    'posts_per_page' => 5,
    'no_found_rows'  => true,
) );

echo 'PUBLISHED LAWYERS: ' . $q->post_count . PHP_EOL;

foreach ( $q->posts as $p ) {
    echo 'ID=' . $p->ID
        . ' name=' . $p->post_name
        . ' url=' . get_permalink( $p->ID )
        . PHP_EOL;
}

/* Check rewrite rules contain bb_lawyer. */
$rules = get_option( 'rewrite_rules' );
$has_rules = is_array( $rules ) && (bool) preg_grep( '/bb_lawyer/', array_keys( $rules ) );
echo 'rewrite_rules has bb_lawyer: ' . var_export( $has_rules, true ) . PHP_EOL;

restore_current_blog();
echo 'DONE' . PHP_EOL;
