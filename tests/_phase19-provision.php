<?php
/*
 * Phase 19 — Multisite provisioning & state reconciliation (idempotent).
 *
 * NOTE ON switch_theme(): it acts on the CURRENTLY SELECTED blog, so it must only
 * ever be called AFTER switch_to_blog(). Calling it before would retheme whichever
 * blog happened to be current. This script is careful about that ordering.
 *
 * Target state:
 *   blog 1 (site 1, no business type)  → its own theme, no bb_business_type
 *   blog 2 (LawFirm)                   → business-builder + law_firm
 *   blog 3 (Medical test site)         → business-builder + medical + medical-modern
 *
 * Never deletes or modifies LawFirm content.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

if ( ! is_multisite() ) {
	echo "NOT MULTISITE\n";
	exit;
}

$domain  = 'medical.builder.test';
$blog_id = 0;

foreach ( get_sites( array( 'number' => 300 ) ) as $site ) {
	if ( $site->domain === $domain ) {
		$blog_id = (int) $site->blog_id;
		break;
	}
}

if ( $blog_id <= 0 ) {
	$created = wpmu_create_blog( $domain, '/', 'Medical Demo', 1, array( 'public' => 1 ), 1 );
	if ( is_wp_error( $created ) ) {
		echo 'ERR ' . $created->get_error_message() . "\n";
		exit;
	}
	$blog_id = (int) $created;
	echo "CREATED blog_id=$blog_id\n";
} else {
	echo "EXISTS blog_id=$blog_id\n";
}

update_option( 'bb_phase19_medical_blog_id', $blog_id );

update_blog_option( $blog_id, 'siteurl', 'http://medical.builder.test' );
update_blog_option( $blog_id, 'home', 'http://medical.builder.test' );

/* ---------------------------------------------------------------------------
 * 1. Site 1 — restore its own theme and remove any BB business type.
 * ------------------------------------------------------------------------ */
switch_to_blog( 1 );

if ( 'astra' !== (string) get_option( 'stylesheet' ) ) {
	switch_theme( 'astra' );
}

if ( '' !== (string) get_option( 'bb_business_type' ) ) {
	update_option( 'bb_business_type', '' );
}

echo 'blog 1 theme=' . get_option( 'stylesheet' ) . ' type=' . get_option( 'bb_business_type' ) . PHP_EOL;

restore_current_blog();

/* ---------------------------------------------------------------------------
 * 2. LawFirm site — must stay exactly as it was.
 * ------------------------------------------------------------------------ */
switch_to_blog( 2 );

if ( 'business-builder' !== (string) get_option( 'stylesheet' ) ) {
	switch_theme( 'business-builder' );
}

if ( 'law_firm' !== (string) get_option( 'bb_business_type' ) ) {
	update_option( 'bb_business_type', 'law_firm' );
}

echo 'blog 2 theme=' . get_option( 'stylesheet' ) . ' type=' . get_option( 'bb_business_type' )
	. ' preset=' . (string) get_theme_mod( 'bb_theme_preset', '' ) . PHP_EOL;

restore_current_blog();

/* ---------------------------------------------------------------------------
 * 3. Medical test site — shared theme, medical business type, Medical preset.
 * ------------------------------------------------------------------------ */
switch_to_blog( $blog_id );

update_option( 'blog_public', 1 );

if ( ! get_option( 'permalink_structure' ) ) {
	update_option( 'permalink_structure', '/%postname%/' );
}

if ( 'business-builder' !== (string) get_option( 'stylesheet' ) ) {
	switch_theme( 'business-builder' );
}

if ( 'medical' !== (string) get_option( 'bb_business_type' ) ) {
	update_option( 'bb_business_type', 'medical' );
}

/* The Medical proof selects its design style via the SITE-LEVEL theme mod. */
set_theme_mod( 'bb_theme_preset', 'medical-modern' );

/* Demo domain entities. */
if ( ! get_posts( array( 'post_type' => 'bb_doctor', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {

	$doctors = array(
		'Dr. Layla Haddad' => 'Consultant Cardiologist',
		'Dr. Omar Nasser'  => 'Interventional Cardiologist',
		'Dr. Sara Khalil'  => 'Pediatric Specialist',
	);

	$order = 1;

	foreach ( $doctors as $name => $title ) {

		$id = wp_insert_post(
			array(
				'post_type'    => 'bb_doctor',
				'post_title'   => $name,
				'post_status'  => 'publish',
				'post_excerpt' => 'Experienced specialist dedicated to patient-centred care.',
			)
		);

		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_bb_doctor_title', $title );
			update_post_meta( $id, '_bb_doctor_display_order', $order );
		}

		$order++;
	}
}

if ( ! get_posts( array( 'post_type' => 'bb_medical_service', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {

	$services = array( 'Cardiology Consultation', 'Diagnostic Imaging', 'Pediatric Care' );

	$order = 1;

	foreach ( $services as $service ) {

		$id = wp_insert_post(
			array(
				'post_type'    => 'bb_medical_service',
				'post_title'   => $service,
				'post_status'  => 'publish',
				'post_excerpt' => 'Comprehensive medical service delivered by our clinical team.',
			)
		);

		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_bb_medical_service_display_order', $order );

			if ( 1 === $order ) {
				update_post_meta( $id, '_bb_medical_service_featured', '1' );
			}
		}

		$order++;
	}
}

/* Front page: GLOBAL sections + MEDICAL sections composed in one page. */
$page    = get_page_by_path( 'home' );
$page_id = $page ? (int) $page->ID : 0;

if ( $page_id <= 0 ) {
	$ids     = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) );
	$page_id = $ids ? (int) $ids[0] : 0;
}

if ( $page_id <= 0 ) {
	$page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_title'  => 'Home',
			'post_name'   => 'home',
			'post_status' => 'publish',
		)
	);
}

if ( $page_id && ! is_wp_error( $page_id ) ) {

	$sections = array(
		array( 'id' => 'm-hero', 'type' => 'hero', 'settings' => array( 'alignment' => 'center', 'min_height' => 520 ), 'content' => array( 'title' => 'Modern Care, Close to Home', 'subheading' => 'Medical Centre', 'description' => 'Comprehensive medical services delivered by experienced specialists.', 'button_text' => 'Book a Visit', 'button_url' => '#' ) ),
		array( 'id' => 'm-about', 'type' => 'about', 'settings' => array(), 'content' => array( 'title' => 'About Our Clinic', 'description' => 'We combine clinical excellence with a patient-first approach.' ) ),
		array( 'id' => 'm-docs', 'type' => 'doctors', 'settings' => array( 'variant' => 'grid', 'card_variant' => 'compact', 'columns' => '3', 'limit' => 0, 'order' => 'asc' ), 'content' => array( 'title' => 'Our Doctors', 'description' => 'Meet the specialists who will look after you.' ) ),
		array( 'id' => 'm-svc', 'type' => 'medical_services', 'settings' => array( 'variant' => 'featured', 'card_variant' => 'featured', 'columns' => '3', 'limit' => 0, 'order' => 'asc' ), 'content' => array( 'title' => 'Medical Services', 'description' => 'A full range of clinical services under one roof.' ) ),
		array( 'id' => 'm-feat', 'type' => 'features', 'settings' => array( 'columns' => '3' ), 'content' => array( 'title' => 'Why Choose Us' ) ),
		array( 'id' => 'm-cta', 'type' => 'cta', 'settings' => array(), 'content' => array( 'title' => 'Ready to book your visit?', 'button_text' => 'Contact Us', 'button_url' => '#' ) ),
		array( 'id' => 'm-contact', 'type' => 'contact', 'settings' => array(), 'content' => array( 'title' => 'Contact', 'phone' => '+000 000 0000', 'email' => 'clinic@example.test' ) ),
		array( 'id' => 'm-footer', 'type' => 'footer', 'settings' => array(), 'content' => array( 'title' => 'Medical Demo' ) ),
	);

	update_post_meta( $page_id, '_bb_page_sections', $sections );
	update_post_meta( $page_id, '_bb_page_template', 'default' );

	/*
	 * The Builder is opt-in per page: Plugin::render_builder_content() only
	 * renders a page whose `_bb_builder_enabled` meta is '1'
	 * (PageManager::is_builder_page()). Without this the page renders as plain
	 * content even though its sections are stored.
	 */
	update_post_meta( $page_id, '_bb_builder_enabled', '1' );

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_id );
}

echo 'blog ' . $blog_id
	. ' theme=' . get_option( 'stylesheet' )
	. ' type=' . get_option( 'bb_business_type' )
	. ' preset=' . (string) get_theme_mod( 'bb_theme_preset', '' )
	. ' front=' . get_option( 'show_on_front' ) . '/' . (int) get_option( 'page_on_front' )
	. ' doctors=' . wp_count_posts( 'bb_doctor' )->publish
	. ' services=' . wp_count_posts( 'bb_medical_service' )->publish . PHP_EOL;

restore_current_blog();

echo "BLOG_ID=$blog_id\nDONE\n";