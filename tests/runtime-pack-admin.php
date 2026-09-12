<?php
/* Real WP runtime: pack boots with new deps, ConsultationAdmin registers, actions resolve. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

switch_to_blog( 2 );
do_action( 'init' );

echo 'law_firm business type: ' . var_export( get_option( 'bb_business_type' ), true ) . PHP_EOL;
echo 'bb_lawyer registered: ' . var_export( post_type_exists( 'bb_lawyer' ), true ) . PHP_EOL;
echo 'bb_consultation registered: ' . var_export( post_type_exists( 'bb_consultation' ), true ) . PHP_EOL;
echo 'bb_practice_area registered: ' . var_export( taxonomy_exists( 'bb_practice_area' ), true ) . PHP_EOL;

/* The admin action must be hooked (admin_post_<action>). */
$hooked = has_action( 'admin_post_bb_consultation_action' );
echo 'consultation action hooked: ' . var_export( false !== $hooked, true ) . PHP_EOL;

/* ConsultationMeta resolution on the real broken record (post 63). */
require_once ABSPATH . 'wp-content/plugins/business-builder-core/packs/LawFirm/PostTypes/ConsultationMeta.php';
$stored = get_post_meta( 63, '_bb_consultation_practice_area', true );
$term = BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta::resolve_practice_area( $stored );
echo 'practice area raw: ' . substr( (string) $stored, 0, 24 ) . PHP_EOL;
echo 'practice area resolved: ' . ( $term instanceof WP_Term ? $term->name : '(null)' ) . PHP_EOL;

restore_current_blog();
echo 'DONE' . PHP_EOL;
