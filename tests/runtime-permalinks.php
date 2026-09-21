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

/*
 * Check rewrite rules contain the bb_lawyer query var.
 *
 * WordPress stores a CPT single as key="lawyers/([^/]+).../" and
 * value="index.php?bb_lawyer=$matches[1]", so the query var lives in the
 * VALUE, not the key. Grepping the KEYS always returned false even when
 * the rules were healthy - scan the targets (values) instead.
 */
$rules = get_option( 'rewrite_rules' );
$has_rules = false;

if ( is_array( $rules ) ) {
    foreach ( $rules as $rule_target ) {
        if ( false !== strpos( (string) $rule_target, 'bb_lawyer' ) ) {
            $has_rules = true;
            break;
        }
    }
}

echo 'rewrite_rules has bb_lawyer: ' . var_export( $has_rules, true ) . PHP_EOL;

restore_current_blog();
echo 'DONE' . PHP_EOL;
