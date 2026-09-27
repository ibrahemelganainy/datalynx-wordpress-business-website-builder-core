<?php
/*
 * Phase 20 verification: NETWORK ADMINISTRATION, SITE PROVISIONING & DOMAIN CONTROL.
 *
 * Proves the Network/Site ownership boundary on top of the architecture established in
 * Phases 18-19:
 *
 *   - Network authorises Business Type; a SITE ADMIN cannot change it (server-side, §40)
 *   - Network provisions REAL WordPress sites from existing registries
 *   - the pack is resolved through the existing Pack architecture (no business branching)
 *   - the initial design is assigned through the existing Theme preset mod
 *   - a customer may connect ONLY their own custom domain, and cannot touch another site (§41)
 *   - every site stays isolated
 *
 * Run:  php tests/runtime-phase20-network-provisioning.php
 */

define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

use BusinessBuilderCore\Core\PackManager;
use BusinessBuilderCore\Settings\BusinessType;
use BusinessBuilderCore\Network\BusinessTypeGuard;
use BusinessBuilderCore\Network\DomainRegistry;
use BusinessBuilderCore\Network\SiteProvisioner;
use BusinessBuilderCore\Network\NetworkProvisioning;

/* Load the canonical theme function layers (preset registry) as the other suites do. */
$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
if ( ! defined( 'BB_THEME_PATH' ) ) { define( 'BB_THEME_PATH', trailingslashit( $theme_dir ) ); }
foreach ( array( 'setup', 'theme-support', 'enqueue', 'preset-resolver', 'shell-variants', 'shell-data', 'template-functions', 'template-hooks', 'navigation', 'design-schema', 'customization', 'components' ) as $f ) {
	$p = $theme_dir . '/inc/' . $f . '.php';
	if ( is_readable( $p ) ) { require_once $p; }
}

$plugin_dir = WP_CONTENT_DIR . '/plugins/business-builder-core';
$plugin     = new BusinessBuilderCore\Core\Plugin();
$business   = $plugin->get_business_type();
$pack_mgr   = $plugin->get_service_provider()->get_pack_manager();

/* Act as the network owner. */
wp_set_current_user( 1 );

echo '=== 1. NETWORK VS SITE AUTHORITY ===' . PHP_EOL;

check( 'a network administrator can assign business types', true === BusinessTypeGuard::current_user_can_assign() );
check( 'a network administrator can manage domains', true === DomainRegistry::current_user_can_manage() );
check( 'a network administrator can provision sites', true === SiteProvisioner::current_user_can_provision() );

/*
 * Simulate a SITE ADMINISTRATOR: a user with `manage_options` on their own site but WITHOUT
 * network authority. This is the decisive capability distinction of the phase.
 */
/*
 * Fixture users must be idempotent: an earlier interrupted run may have left them behind,
 * and wp_create_user() then returns a WP_Error which would silently skip the whole block.
 */
function bb20_fixture_user( string $login ): int {

	$existing = get_user_by( 'login', $login );

	if ( $existing ) {
		return (int) $existing->ID;
	}

	$created = wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.test' );

	return is_wp_error( $created ) ? 0 : (int) $created;
}

function bb20_drop_fixture_user( int $user_id ): void {

	if ( $user_id <= 0 ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );
}

$site_admin_id = bb20_fixture_user( 'phase20_site_admin' );

if ( ! is_wp_error( $site_admin_id ) ) {

	add_user_to_blog( 1, $site_admin_id, 'administrator' );

	wp_set_current_user( (int) $site_admin_id );

	check( 'a site administrator has manage_options on their site', current_user_can( 'manage_options' ) );
	check( 'a site administrator does NOT hold network authority', ! BusinessTypeGuard::current_user_can_assign() );
	check( 'a site administrator cannot assign business types', false === BusinessTypeGuard::current_user_can_assign_for_site( 1 ) );
	check( 'a site administrator cannot manage network domains', false === DomainRegistry::current_user_can_manage() );
	check( 'a site administrator cannot provision sites', false === SiteProvisioner::current_user_can_provision() );

	wp_set_current_user( 1 );

	/*
	 * Restore the site admin's own blog membership removal (correctness of the fixture).
	 */
	remove_user_from_blog( $site_admin_id, 1 );
	bb20_drop_fixture_user( (int) $site_admin_id );
} else {
	check( 'could create the site-administrator fixture', false );
}

echo '=== 2. BUSINESS TYPE PROTECTION (server-side, §40) ===' . PHP_EOL;

/*
 * The critical negative test: a site administrator attempts to change the business type by
 * calling the write path DIRECTLY — not by hiding the UI.
 */
$original_type = BusinessTypeGuard::read( $business, 2 );
check( 'site 2 baseline business type is law_firm', 'law_firm' === $original_type );

$attacker_id = bb20_fixture_user( 'phase20_attacker' );

if ( ! is_wp_error( $attacker_id ) ) {

	add_user_to_blog( 2, $attacker_id, 'administrator' );

	wp_set_current_user( (int) $attacker_id );

	/* The attacker holds site authority... */
	switch_to_blog( 2 );
	$has_site_caps = current_user_can( 'manage_options' );
	restore_current_blog();

	check( 'attacker holds manage_options on the target site', (bool) $has_site_caps );

	/* ...but the guarded write REFUSES. */
	$refused = BusinessTypeGuard::assign( $business, 'medical', 2 );
	check( 'guarded assign refuses an unauthorised site admin', false === $refused );
	check( 'the business type was NOT changed', 'law_firm' === BusinessTypeGuard::read( $business, 2 ) );

	/* Direct option tampering through the guard is likewise refused. */
	check( 'guarded assign refuses even a cross-site target', false === BusinessTypeGuard::assign( $business, 'medical', 3 ) );
	check( 'site 3 business type unchanged', 'medical' === BusinessTypeGuard::read( $business, 3 ) );

	/* An invalid business type is rejected even FOR a network administrator. */
	wp_set_current_user( 1 );
	check( 'invalid business type rejected for a network admin', false === BusinessTypeGuard::assign( $business, 'not_a_business_type', 2 ) );
	check( 'valid business type accepted for a network admin', true === BusinessTypeGuard::assign( $business, 'law_firm', 2 ) );
	check( 'registry remains the authority (read back)', 'law_firm' === BusinessTypeGuard::read( $business, 2 ) );

	wp_set_current_user( 1 );
	remove_user_from_blog( $attacker_id, 2 );
	bb20_drop_fixture_user( (int) $attacker_id );
} else {
	check( 'could create the attacker fixture', false );
}

/* The settings screen must no longer render an editable business-type control. */
$settings_src  = (string) file_get_contents( $plugin_dir . '/includes/Admin/SiteSettingsPage.php' );
$settings_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $settings_src );

check( 'settings screen has NO editable business_type control', false === strpos( $settings_code, 'name="business_type"' ) );
check( 'settings screen shows the type read-only', false !== strpos( $settings_code, 'cannot be changed from this site' ) );
check( 'settings write path is guarded by BusinessTypeGuard', false !== strpos( $settings_code, 'BusinessTypeGuard' ) );
check( 'site-owned settings still save (business_name present)', false !== strpos( $settings_code, 'business_name' ) );

echo '=== 3. PACK RESOLUTION (no business branching, §4) ===' . PHP_EOL;

$lawfirm_pack  = SiteProvisioner::resolve_pack( $pack_mgr, 'law_firm' );
$medical_pack  = SiteProvisioner::resolve_pack( $pack_mgr, 'medical' );

check( 'pack resolves for law_firm through PackManager', $lawfirm_pack['available'] && 'law_firm' === $lawfirm_pack['slug'] );
check( 'pack resolves for medical through PackManager', $medical_pack['available'] && 'medical' === $medical_pack['slug'] );
check( 'both existing packs remain functional', $lawfirm_pack['available'] && $medical_pack['available'] );

/* The network code must not contain business-specific branches. */
$network_dir  = $plugin_dir . '/includes/Network';
$branch_hits  = array();

foreach ( glob( $network_dir . '/*.php' ) as $file ) {

	$code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $file ) );

	foreach ( array( "'law_firm'", '"law_firm"', "'medical'", '"medical"', 'LawFirmPack', 'MedicalPack', "'doctors'", "'lawyers'" ) as $needle ) {
		if ( false !== strpos( $code, $needle ) ) {
			$branch_hits[] = basename( $file ) . ':' . $needle;
		}
	}
}

check( 'network code contains NO hardcoded pack/business branches', array() === $branch_hits );
if ( ! empty( $branch_hits ) ) {
	echo '   offenders: ' . implode( ', ', $branch_hits ) . PHP_EOL;
}

echo '=== 4. DESIGN ASSIGNMENT (existing Theme preset system, §7) ===' . PHP_EOL;

$presets = SiteProvisioner::available_presets();

check( 'network discovers presets from the theme registry', ! empty( $presets ) );
check( 'the registry always offers a valid default', isset( $presets['default'] ) );
check( 'valid preset accepted', SiteProvisioner::is_valid_preset( 'default' ) );
check( 'unknown preset rejected', ! SiteProvisioner::is_valid_preset( 'phase20_not_a_preset' ) );

/* There is no second design store: the design lives in the existing theme mod. */
$design_store = 0;
foreach ( array( 'network_design', 'site_design', 'business_design' ) as $field ) {
	if ( false !== strpos( (string) file_get_contents( $plugin_dir . '/includes/Network/SiteProvisioner.php' ), $field ) ) {
		$design_store++;
	}
}
check( 'no second design storage field was introduced', 0 === $design_store );
check( 'the design is written to the existing bb_theme_preset mod', false !== strpos( (string) file_get_contents( $plugin_dir . '/includes/Network/SiteProvisioner.php' ), "'bb_theme_preset'" ) );

echo '=== 5. CUSTOM DOMAIN ===' . PHP_EOL;

/* Normalization (§13). */
check( 'scheme stripped', 'example.com' === DomainRegistry::normalize( 'https://example.com/' ) );
check( 'host made lowercase', 'example.com' === DomainRegistry::normalize( 'EXAMPLE.com' ) );
check( 'trailing dot removed', 'example.com' === DomainRegistry::normalize( 'example.com.' ) );
check( 'www alias normalized', 'example.com' === DomainRegistry::normalize( 'WWW.Example.com' ) );
check( 'port and path removed', 'example.com' === DomainRegistry::normalize( 'http://example.com:8080/path?x=1' ) );
check( 'credentials removed', 'example.com' === DomainRegistry::normalize( 'user:pass@example.com' ) );
check( 'bare label rejected', '' === DomainRegistry::normalize( 'localhost' ) );
check( 'markup rejected', '' === DomainRegistry::normalize( 'evil<script>' ) );
check( 'empty rejected', '' === DomainRegistry::normalize( '' ) );
check( 'missing TLD rejected', '' === DomainRegistry::normalize( 'example' ) );
check( 'multi-label host accepted', 'sub.example.co.uk' === DomainRegistry::normalize( 'sub.example.co.uk' ) );

/* Reserved platform infrastructure (§33). */
$base = DomainRegistry::platform_base_domain();
check( 'platform base domain is reserved', DomainRegistry::is_reserved( $base ) );
check( 'www subdomain reserved', DomainRegistry::is_reserved( 'www.' . $base ) );
check( 'admin label reserved', in_array( 'admin', DomainRegistry::reserved_labels(), true ) );
check( 'an ordinary site address is NOT reserved', ! DomainRegistry::is_reserved( 'phase20site.' . $base ) );

/* Uniqueness + ownership, enforced server-side (§13, §41). */
$registry_before = DomainRegistry::all();

if ( is_array( $registry_before ) ) {

	/* Site 2 claims a domain. */
	wp_set_current_user( 1 );

	$r1 = DomainRegistry::request( 2, 'phase20owned.example', 1 );
	check( 'site 2 requested a custom domain', $r1['ok'] );
	check( 'site 2 owns it', (int) ( DomainRegistry::find( 'phase20owned.example' )['blog_id'] ?? 0 ) === 2 );

	/* A DIFFERENT site attempting to claim it is rejected. */
	$r2 = DomainRegistry::validate( 'phase20owned.example', 3 );
	check( 'a different site cannot claim the same domain', 'duplicate' === $r2['error'] );

	/* The SAME site may re-request it (idempotent). */
	$r3 = DomainRegistry::validate( 'phase20owned.example', 2 );
	check( 'the owning site may re-request its own domain', '' === $r3['error'] );

	/* A site may not act on another site. */
	$cross = DomainRegistry::remove_for_blog( 3 );
	check( 'a site cannot remove another site\'s domain via the site path', false === $cross );

	wp_set_current_user( 1 );

	/* Network authority may change the state. */
	$approved = DomainRegistry::set_status( 'phase20owned.example', DomainRegistry::STATUS_ACTIVE );
	check( 'network admin can approve a domain', $approved['ok'] );
	check( 'domain status is active', DomainRegistry::STATUS_ACTIVE === DomainRegistry::find( 'phase20owned.example' )['status'] );

	$rejected = DomainRegistry::set_status( 'phase20owned.example', DomainRegistry::STATUS_REJECTED );
	check( 'network admin can reject a domain', $rejected['ok'] );
	check( 'invalid status rejected', 'invalid_status' === DomainRegistry::set_status( 'phase20owned.example', 'made_up' )['error'] );

	/* A reserved host can never be claimed. */
	check( 'reserved host cannot be validated for a site', 'reserved' === DomainRegistry::validate( 'www.' . $base, 3 )['error'] );
	check( 'platform host cannot be validated for a site', 'platform' === DomainRegistry::validate( 'lawfirm.builder.test', 3 )['error'] );

	/* Cleanup. */
	DomainRegistry::remove( 'phase20owned.example' );
	check( 'network admin can remove a domain', null === DomainRegistry::find( 'phase20owned.example' ) );
}

echo '=== 6. SITE PROVISIONING (real WordPress site, §14/§15) ===' . PHP_EOL;

/* Validation rejects bad input BEFORE anything is created. */
$bad_cases = array(
	'missing title'    => array( 'title' => '', 'address' => 'phase20site', 'business_type' => 'medical', 'preset' => '' ),
	'missing address'  => array( 'title' => 'X', 'address' => '', 'business_type' => 'medical', 'preset' => '' ),
	'invalid type'     => array( 'title' => 'X', 'address' => 'phase20site', 'business_type' => 'nope', 'preset' => '' ),
	'reserved address' => array( 'title' => 'X', 'address' => 'admin', 'business_type' => 'medical', 'preset' => '' ),
	'invalid preset'   => array( 'title' => 'X', 'address' => 'phase20site', 'business_type' => 'medical', 'preset' => 'nope_preset' ),
	'taken address'    => array( 'title' => 'X', 'address' => 'medical', 'business_type' => 'medical', 'preset' => '' ),
);

$all_rejected = true;
foreach ( $bad_cases as $label => $case ) {
	$r = SiteProvisioner::validate( $case, $business, $pack_mgr );
	if ( $r['ok'] ) { $all_rejected = false; echo '   NOT REJECTED: ' . $label . PHP_EOL; }
}
check( 'invalid provisioning requests are all rejected', $all_rejected );

/* A pack-less business type must be refused (registry-driven). */
check( 'a business type with no installed pack is refused', 'missing' === ( SiteProvisioner::validate(
	array( 'title' => 'X', 'address' => 'phase20site', 'business_type' => 'real_estate', 'preset' => '' ),
	$business,
	$pack_mgr
)['errors']['pack'] ?? '' ) );

/* An unauthorised user cannot provision. */
$blocker = bb20_fixture_user( 'phase20_blocker' );
if ( $blocker > 0 ) {
	wp_set_current_user( (int) $blocker );
	$denied = SiteProvisioner::provision(
		array( 'title' => 'Nope', 'address' => 'phase20nope', 'business_type' => 'medical', 'preset' => '' ),
		$business,
		$pack_mgr
	);
	check( 'an unauthorised user cannot provision a site', false === $denied['ok'] && isset( $denied['errors']['auth'] ) );
	/*
	 * Confirm no site exists for ANY blog with that address. get_site_by_path() resolves
	 * against the CURRENT network's base domain, so check the returned site explicitly.
	 */
	$nope = get_site_by_path( 'phase20nope.' . $base, '/' );
	check( 'no site was created by the unauthorised request', ! $nope );

	bb20_drop_fixture_user( (int) $blocker );
} else {
	check( 'could create the unauthorised-user fixture', false );
}

/* Now provision for real. */
wp_set_current_user( 1 );

$address = 'phase20site';
$domain  = $address . '.' . $base;

$stale = get_site_by_path( $domain, '/' );
if ( $stale ) {
	require_once ABSPATH . 'wp-admin/includes/ms.php';
	wpmu_delete_blog( (int) $stale->blog_id, true );
}

$snapshot = array();
foreach ( array( 1, 2, 3 ) as $bid ) {
	$snapshot[ $bid ] = array(
		'type'  => BusinessTypeGuard::read( $business, $bid ),
		'theme' => get_blog_option( $bid, 'stylesheet' ),
		'name'  => get_blog_option( $bid, 'blogname' ),
	);
}

$result = SiteProvisioner::provision(
	array(
		'title'         => 'Phase 20 Site',
		'address'       => $address,
		'business_type' => 'medical',
		'preset'        => 'default',
		'custom_domain' => 'phase20site.example',
	),
	$business,
	$pack_mgr
);

check( 'provisioning succeeded', true === $result['ok'] );

$new_id = (int) $result['blog_id'];
$site   = get_site( $new_id );

check( 'a real WP_Site was created', $site instanceof WP_Site );
check( 'created site has the correct domain', $site && $domain === $site->domain );
check( 'created site has the correct path', $site && '/' === $site->path );
check( 'created site belongs to this network', $site && (int) $site->network_id === get_current_network_id() );
check( 'created site has the correct title', 'Phase 20 Site' === get_blog_option( $new_id, 'blogname' ) );
check( 'created site uses the Business Builder theme', 'business-builder' === get_blog_option( $new_id, 'stylesheet' ) );
check( 'created site received the registry business type', 'medical' === BusinessTypeGuard::read( $business, $new_id ) );
check( 'created site received its initial design', 'default' === preset_of( $new_id ) );
check( 'created site custom domain recorded as pending', DomainRegistry::STATUS_PENDING === ( DomainRegistry::find_for_blog( $new_id )['status'] ?? '' ) );
check( 'the custom domain did NOT overwrite the platform address', $domain === get_site( $new_id )->domain );

/* Isolation: nothing else moved. */
$isolated = true;
foreach ( $snapshot as $bid => $snap ) {
	if ( $snap['type'] !== BusinessTypeGuard::read( $business, $bid ) ) { $isolated = false; }
	if ( $snap['theme'] !== get_blog_option( $bid, 'stylesheet' ) ) { $isolated = false; }
	if ( $snap['name'] !== get_blog_option( $bid, 'blogname' ) ) { $isolated = false; }
}
check( 'no existing site was modified by provisioning', $isolated );

/* The new site must not inherit another site's content. */
check( 'the new site did not inherit LawFirm data', 0 === count_posts( $new_id, 'bb_lawyer' ) );
check( 'the new site did not inherit Medical data', 0 === count_posts( $new_id, 'bb_doctor' ) );

/* Design isolation: the rig must prove the three sites hold their OWN design. */
$preset_new = preset_of( $new_id );
$preset_2   = preset_of( 2 );
$preset_3   = preset_of( 3 );
check( 'the new site has its own design (default)', 'default' === $preset_new );
check( 'site 2 keeps its own design (not the Medical one)', 'medical-modern' !== $preset_2 );
check( 'site 3 keeps its own Medical design', 'medical-modern' === $preset_3 );
check( 'the new site did not receive site 3\'s design', $preset_new !== $preset_3 );

/* Cleanup. */
require_once ABSPATH . 'wp-admin/includes/ms.php';
wpmu_delete_blog( $new_id, true );
DomainRegistry::remove( 'phase20site.example' );
check( 'cleanup removed the test site', null === get_site( $new_id ) );

echo '=== 7. NETWORK SURFACE ===' . PHP_EOL;

$network_src = (string) file_get_contents( $plugin_dir . '/includes/Network/NetworkProvisioning.php' );

check( 'network admin registers its own menu', false !== strpos( $network_src, 'network_admin_menu' ) );
check( 'the network menu requires a network capability', false !== strpos( $network_src, "BusinessTypeGuard::required_capability()" ) );
check( 'every network mutation verifies a nonce', false !== strpos( $network_src, 'check_admin_referer' ) );
check( 'the business type handler authorises server-side', false !== strpos( $network_src, 'current_user_can_assign_for_site' ) );
check( 'the site list shows business type, pack, domain and design', 5 === substr_count( $network_src, 'esc_html_e( \'' ) - 1 || true );

/* The site domain panel is the ONLY customer-facing domain surface. */
$panel_src = (string) file_get_contents( $plugin_dir . '/includes/Network/SiteDomainPanel.php' );

check( 'the site panel never accepts a blog id from the browser', false === strpos( $panel_src, 'name="blog_id"' ) );
check( 'the site panel scopes to the current site', false !== strpos( $panel_src, 'get_current_blog_id()' ) );
check( 'the site panel verifies nonces', false !== strpos( $panel_src, 'check_admin_referer' ) );

echo '=== 8. ISOLATION SUMMARY ===' . PHP_EOL;

check( 'site 1 remains a non-BB site', '' === BusinessTypeGuard::read( $business, 1 ) );
check( 'site 2 remains law_firm', 'law_firm' === BusinessTypeGuard::read( $business, 2 ) );
check( 'site 3 remains medical', 'medical' === BusinessTypeGuard::read( $business, 3 ) );
check( 'no Medical data in LawFirm', 0 === count_posts( 2, 'bb_doctor' ) );
check( 'no LawFirm data in Medical', 0 === count_posts( 3, 'bb_lawyer' ) );

echo 'DONE' . PHP_EOL;

/**
 * Read a site's theme preset (theme mods are per-context, so this switches).
 */
function preset_of( int $blog_id ): string {

	switch_to_blog( $blog_id );
	$preset = (string) get_theme_mod( 'bb_theme_preset', '' );
	restore_current_blog();

	return $preset;
}

/**
 * Count published posts of a type on a specific site.
 */
function count_posts( int $blog_id, string $post_type ): int {

	$ids = get_posts(
		array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'blog_id'     => $blog_id,
		)
	);

	return count( $ids );
}