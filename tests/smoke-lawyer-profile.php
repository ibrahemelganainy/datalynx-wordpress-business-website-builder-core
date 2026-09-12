<?php
/**
 * Phase 9 verification: LawyerProfile class resolves and its
 * get_data() contract maps every meta key correctly.
 * Runs standalone with minimal WordPress shims.
 */

$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $root );
}

if ( ! defined( 'BB_CORE_PATH' ) ) {
    define( 'BB_CORE_PATH', $root . '/' );
    }

$meta_fixture = array(
    '_bb_lawyer_title'          => 'Senior Partner',
    '_bb_lawyer_experience'     => '15',
    '_bb_lawyer_license_number' => 'LIC-123',
    '_bb_lawyer_education'      => 'LLB, Cairo University',
    '_bb_lawyer_languages'      => 'Arabic, English',
    '_bb_lawyer_phone'          => '+20100000',
    '_bb_lawyer_whatsapp'       => '+20100000',
    '_bb_lawyer_email'          => 'a@example.com',
    '_bb_lawyer_linkedin'       => 'https://linkedin.com/in/x',
    '_bb_lawyer_facebook'       => 'https://facebook.com/x',
    '_bb_lawyer_x'              => 'https://x.com/x',
);

if ( ! class_exists( 'WP_Post' ) ) {
    class WP_Post {
        public $ID;
        public $post_title;
        public $post_excerpt;
        public $post_content;
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $id ) {
        $p = new WP_Post();
        $p->ID = $id;
        $p->post_title = 'Ahmed Mohamed';
        $p->post_excerpt = 'Short bio.';
        $p->post_content = 'Full biography.';
        return $p;
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $id, $key, $single = false ) {
        global $meta_fixture;
        return isset( $meta_fixture[ $key ] ) ? $meta_fixture[ $key ] : '';
    }
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
    function get_post_thumbnail_id( $id ) { return 42; }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $id ) { return 'https://example.com/lawyers/ahmed-mohamed/'; }
}

if ( ! function_exists( 'get_the_terms' ) ) {
    function get_the_terms( $id, $tax ) {
        $t = new WP_Post();
        $t->name = 'Corporate Law';
        $t->term_id = 7;
        return array( $t );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $x ) { return false; }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $h, $c, $p = 10, $a = 1 ) { return true; }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $h, $c, $p = 10, $a = 1 ) { return true; }
}

require_once $root . '/packs/LawFirm/Frontend/LawyerProfile.php';

use BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile;

$ok = true;

if ( ! class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\Frontend\\LawyerProfile' ) ) {
    echo "CLASS MISSING\n";
    exit( 1 );
}

$profile = new LawyerProfile();
$profile->register();

$data = LawyerProfile::get_data( 99 );

$expected = array( 'name', 'title', 'experience', 'license_number', 'education', 'languages', 'phone', 'whatsapp', 'email', 'linkedin', 'facebook', 'x', 'short_bio', 'full_bio', 'photo_id', 'practice_areas', 'permalink' );

foreach ( $expected as $key ) {
    if ( ! array_key_exists( $key, $data ) ) {
        echo "MISSING DATA KEY: $key\n";
        $ok = false;
    }
}

echo "name: " . $data['name'] . "\n";
echo "title: " . $data['title'] . "\n";
echo "experience: " . $data['experience'] . "\n";
echo "license_number: " . $data['license_number'] . "\n";
echo "education: " . $data['education'] . "\n";
echo "languages: " . $data['languages'] . "\n";
echo "phone: " . $data['phone'] . "\n";
echo "email: " . $data['email'] . "\n";
echo "linkedin: " . $data['linkedin'] . "\n";
echo "short_bio: " . $data['short_bio'] . "\n";
echo "photo_id: " . $data['photo_id'] . "\n";
echo "areas: " . count( $data['practice_areas'] ) . "\n";

$template = $root . '/templates/single-bb_lawyer.php';
echo 'template exists: ' . ( file_exists( $template ) ? 'yes' : 'NO' ) . "\n";
if ( ! file_exists( $template ) ) {
    $ok = false;
}

echo $ok ? "\nRESULT: OK\n" : "\nRESULT: FAIL\n";
