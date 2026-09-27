<?php

namespace BusinessBuilderCore\Network;

use BusinessBuilderCore\Settings\BusinessType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business Type write-authorization guard (Phase 20 §2, §3, §16, §24).
 *
 * THE RULE:
 *
 *   Network Admin  → may assign / change a site's Business Type
 *   Site Admin     → may SEE it, must NOT be able to change it
 *
 * AUDIT FINDING (docs/phase-20-network-provisioning-audit.md §2):
 * before Phase 20, `SiteSettingsPage::save_settings()` wrote the business type with only
 * `current_user_can( 'manage_options' )` — a capability every multisite site administrator
 * holds on their own site. A customer could therefore POST `business_type=medical` and
 * reclassify their whole website. Hiding the dropdown would not fix that: the request can be
 * crafted directly, so the fix must be SERVER-SIDE AUTHORIZATION.
 *
 * This class owns that authority. `bb_business_type` remains the single, site-local source of
 * truth (Phase 20 §2 forbids a second storage system); only the WRITE gains an owner.
 *
 * Nothing here reads a blog id from the browser: the target is always resolved server-side.
 */
class BusinessTypeGuard {

	/**
	 * The capability required to change a site's Business Type.
	 *
	 * `manage_network` is the network-administration capability and is not granted to site
	 * administrators, which is exactly the boundary Phase 20 requires. `is_super_admin()`
	 * is additionally honoured because a super admin always holds network authority.
	 *
	 * @return string
	 */
	public static function required_capability(): string {

		return 'manage_network';
	}

	/**
	 * Whether the current user may change Business Types at all.
	 *
	 * @return bool
	 */
	public static function current_user_can_assign(): bool {

		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		return current_user_can( self::required_capability() );
	}

	/**
	 * Whether the current user may change the Business Type of a SPECIFIC site.
	 *
	 * @param int $blog_id Target site.
	 * @return bool
	 */
	public static function current_user_can_assign_for_site( int $blog_id ): bool {

		$blog_id = absint( $blog_id );

		if ( $blog_id <= 0 ) {
			return false;
		}

		if ( ! self::current_user_can_assign() ) {
			return false;
		}

		/*
		 * A network administrator must still be authorised for the target site itself,
		 * so a capability on site A never grants a write to site B.
		 */
		if ( is_multisite() && function_exists( 'get_blog_details' ) && ! get_blog_details( $blog_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Assign a site's Business Type — the ONLY authorised write path.
	 *
	 * Validates the slug against the existing registry (via BusinessType::set_current(), which
	 * already rejects unknown slugs) and enforces network authority before writing.
	 *
	 * @param BusinessType $business_type Registry service.
	 * @param string       $slug          Business type slug to assign.
	 * @param int          $blog_id       Target site (0 = current site).
	 * @return bool True when assigned.
	 */
	public static function assign( BusinessType $business_type, string $slug, int $blog_id = 0 ): bool {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		if ( ! self::current_user_can_assign_for_site( $blog_id ) ) {
			return false;
		}

		$slug = sanitize_key( $slug );

		/*
		 * The registry is the authority on what a valid business type is. An empty slug
		 * (clearing the assignment) is allowed for a network administrator; anything else
		 * must exist.
		 */
		if ( '' !== $slug && ! $business_type->exists( $slug ) ) {
			return false;
		}

		$switched = false;

		if ( $blog_id !== get_current_blog_id() && is_multisite() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$result = '' === $slug
			? (bool) update_option( $business_type->get_option_name(), '' )
			: $business_type->set_current( $slug );

		/*
		 * update_option()/set_current() return FALSE when the stored value is ALREADY the
		 * requested one — WordPress reports "nothing changed", not "failed". Treat the write
		 * as successful when the stored state matches the request, otherwise re-assigning a
		 * site the type it already has would be reported as an error.
		 */
		if ( ! $result ) {
			$stored = $business_type->get_current();
			$result = ( '' === $slug && null === $stored ) || (string) $stored === $slug;
		}

		if ( $switched ) {
			restore_current_blog();
		}

		return (bool) $result;
	}

	/**
	 * Read a site's Business Type (allowed for everyone — reading is not the risk).
	 *
	 * @param BusinessType $business_type Registry service.
	 * @param int          $blog_id       Target site (0 = current site).
	 * @return string Slug, or '' when unset.
	 */
	public static function read( BusinessType $business_type, int $blog_id = 0 ): string {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		$switched = false;

		if ( $blog_id !== get_current_blog_id() && is_multisite() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$current = $business_type->get_current();

		if ( $switched ) {
			restore_current_blog();
		}

		return null === $current ? '' : (string) $current;
	}

	/**
	 * The business type configuration (label etc.) for display purposes.
	 *
	 * @param BusinessType $business_type Registry service.
	 * @param string       $slug          Business type slug.
	 * @return array<string, mixed>|null
	 */
	public static function describe( BusinessType $business_type, string $slug ): ?array {

		return $business_type->get( $slug );
	}
}