<?php

namespace BusinessBuilderCore\Network;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Domain registry (Phase 20 §9–§13, §31–§33).
 *
 * WHY THIS EXISTS (audit §7): WordPress Multisite has NO custom-domain concept. The only
 * place a host lives is `wp_blogs.domain`, which is the ROUTING identity — writing a
 * customer's purchased domain there would move the site and is explicitly not a mapping
 * (Phase 20 §12/§13: "Do NOT fake domain mapping by merely changing an option").
 *
 * So a custom domain needs its own small, NETWORK-scoped record:
 *
 *   - network scope      → get_site_option()/update_site_option(), never a site option
 *                          (a network-wide mapping must never live in bb_site_settings)
 *   - unique             → one normalized domain maps to AT MOST one site
 *   - owned              → Network approves/rejects/removes; the site only REQUESTS
 *   - auditable          → who requested it and when
 *
 * DELIBERATELY NOT IMPLEMENTED: DNS verification, server aliases, SSL, registrar calls.
 * Nothing in this repository or environment can verify DNS, so the states are limited to what
 * is honestly knowable here (pending / active / rejected). Claiming "verified" because a
 * string was stored would be a lie (Phase 20 §19).
 */
class DomainRegistry {

	/**
	 * Network option holding the registry.
	 */
	public const OPTION = 'bb_network_domains';

	/**
	 * Lifecycle states this environment can actually support.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_ACTIVE   = 'active';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * The states that exist, for UI + validation.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {

		return array( self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_REJECTED );
	}

	/**
	 * The capability required to change a domain's state (Network authority).
	 *
	 * @return string
	 */
	public static function required_capability(): string {

		return 'manage_network';
	}

	/**
	 * Whether the current user holds Network (domain infrastructure) authority.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {

		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		return current_user_can( self::required_capability() );
	}

	/**
	 * Read the whole registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {

		$stored = is_multisite()
			? get_site_option( self::OPTION, array() )
			: get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Persist the registry.
	 *
	 * @param array<string, array<string, mixed>> $domains Registry.
	 * @return bool
	 */
	private static function save( array $domains ): bool {

		return is_multisite()
			? (bool) update_site_option( self::OPTION, $domains )
			: (bool) update_option( self::OPTION, $domains );
	}

	/**
	 * Normalize a domain to a single canonical key.
	 *
	 * Handles (Phase 20 §13): case, whitespace, scheme, path, port, "www.", trailing dot,
	 * unicode/punycode-free hosts, and rejects anything that is not a bare hostname.
	 *
	 * @param string $domain Raw input.
	 * @return string Normalized host, or '' when invalid.
	 */
	public static function normalize( string $domain ): string {

		$domain = trim( $domain );

		if ( '' === $domain ) {
			return '';
		}

		/*
		 * Strip a scheme, any credentials, a port and any path/query/fragment.
		 *
		 * NOTE: the patterns deliberately avoid a delimiter that also appears inside the
		 * character classes. '#' appears inside '[:/?#]', which would terminate the pattern
		 * early and make the trailing text parse as modifiers; '~' is safe for all three.
		 */
		$domain = preg_replace( '~^[a-z][a-z0-9+.\-]*://~i', '', $domain );
		$domain = preg_replace( '~^[^/@]*@~', '', (string) $domain );
		$domain = preg_replace( '~[:/?#].*$~', '', (string) $domain );

		$domain = strtolower( trim( (string) $domain ) );

		/* Trailing dot (fully-qualified form) is not part of the identity. */
		$domain = rtrim( $domain, '.' );

		/* A leading "www." is a common alias of the same site, not a different domain. */
		if ( 0 === strpos( $domain, 'www.' ) ) {
			$domain = substr( $domain, 4 );
		}

		/*
		 * Must be a plausible hostname: at least one dot, only [a-z0-9-] labels, no empty
		 * label, no leading/trailing hyphen, TLD at least 2 characters.
		 */
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $domain ) ) {
			return '';
		}

		if ( ! preg_match( '/\.[a-z]{2,}$/', $domain ) ) {
			return '';
		}

		if ( strlen( $domain ) > 253 ) {
			return '';
		}

		return $domain;
	}

	/**
	 * Validate a normalized domain for association.
	 *
	 * @param string $domain    Raw input.
	 * @param int    $blog_id   Site requesting it.
	 * @param int    $exclude   A registry key to ignore (for re-request by the same site).
	 * @return array{domain:string,error:string} Normalized domain + error code ('' = ok).
	 */
	public static function validate( string $domain, int $blog_id, int $exclude = 0 ): array {

		$normalized = self::normalize( $domain );

		if ( '' === $normalized ) {
			return array( 'domain' => '', 'error' => 'invalid' );
		}

		if ( self::is_reserved( $normalized ) ) {
			return array( 'domain' => '', 'error' => 'reserved' );
		}

		/* One domain may belong to at most ONE site. */
		$existing = self::find( $normalized );

		if ( null !== $existing && (int) $existing['blog_id'] !== absint( $blog_id ) ) {
			return array( 'domain' => '', 'error' => 'duplicate' );
		}

		/*
		 * A host already used by the network itself (wp_blogs.domain) is platform
		 * infrastructure, not a customer domain.
		 */
		if ( self::is_platform_host( $normalized ) ) {
			return array( 'domain' => '', 'error' => 'platform' );
		}

		return array( 'domain' => $normalized, 'error' => '' );
	}

	/**
	 * Platform-reserved hosts that a customer may never claim (Phase 20 §33).
	 *
	 * Derived from the ACTUAL network configuration (DOMAIN_CURRENT_SITE and every host in
	 * wp_blogs) rather than an assumed nickname.
	 *
	 * @param string $domain Normalized domain.
	 * @return bool
	 */
	public static function is_reserved( string $domain ): bool {

		$domain = self::normalize( $domain );

		if ( '' !== $domain && in_array( $domain, self::reserved_labels(), true ) ) {
			return true;
		}

		$base = self::platform_base_domain();

		if ( '' === $base ) {
			return false;
		}

		if ( $domain === $base ) {
			return true;
		}

		/*
		 * NOTE: this deliberately reserves the base domain and the labels in
		 * reserved_labels() ONLY. Ordinary subdomains of the base domain
		 * (<site>.builder.test) are exactly what the network is supposed to create,
		 * so they must NOT be treated as reserved. Tenant-address protection lives in
		 * SiteProvisioner::check_address(), which tests the first label.
		 */
		return false;
	}

	/**
	 * Labels that may never become a site address or a custom domain.
	 *
	 * @return string[]
	 */
	public static function reserved_labels(): array {

		return array(
			'www', 'network', 'admin', 'administrator', 'mail', 'smtp',
			'ns1', 'ns2', 'dns', 'ftp', 'cpanel', 'localhost', 'ip',
			'static', 'cdn', 'assets', 'api', 'app', 'test', 'dev', 'staging',
		);
	}

	/**
	 * Whether the host is already bound in wp_blogs (the network's own routing table).
	 *
	 * @param string $domain Normalized domain.
	 * @return bool
	 */
	public static function is_platform_host( string $domain ): bool {

		if ( ! is_multisite() ) {
			return false;
		}

		foreach ( get_sites( array( 'number' => 1000 ) ) as $site ) {

			$host = strtolower( rtrim( (string) $site->domain, '.' ) );

			if ( $domain === $host ) {
				return true;
			}

			if ( 0 === strpos( $host, 'www.' ) && self::normalize( $host ) === $domain ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The network's own base domain (from DOMAIN_CURRENT_SITE).
	 *
	 * @return string
	 */
	public static function platform_base_domain(): string {

		/*
		 * DOMAIN_CURRENT_SITE is the canonical answer in a real request. In some CLI/
		 * bootstrap contexts the constant is not yet defined, so fall back to the
		 * network's own home URL rather than returning an empty string.
		 */
		if ( defined( 'DOMAIN_CURRENT_SITE' ) && DOMAIN_CURRENT_SITE ) {

			$base = self::normalize( (string) DOMAIN_CURRENT_SITE );

			if ( '' !== $base ) {
				return $base;
			}
		}

		$host = wp_parse_url( network_site_url( '/' ), PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		}

		/* A bare host has no dot, which normalize() rejects; return it verbatim. */
		if ( is_string( $host ) && '' !== $host && false === strpos( $host, '.' ) ) {
			return $host;
		}

		return is_string( $host ) ? self::normalize( $host ) : '';
	}

	/**
	 * Find a registry entry by normalized domain.
	 *
	 * @param string $domain Normalized domain.
	 * @return array<string, mixed>|null
	 */
	public static function find( string $domain ): ?array {

		$domain = self::normalize( $domain );

		if ( '' === $domain ) {
			return null;
		}

		$all = self::all();

		return isset( $all[ $domain ] ) ? $all[ $domain ] : null;
	}

	/**
	 * The entry belonging to a site (a site has at most one custom domain in this phase).
	 *
	 * @param int $blog_id Site.
	 * @return array<string, mixed>|null
	 */
	public static function find_for_blog( int $blog_id ): ?array {

		$blog_id = absint( $blog_id );

		if ( $blog_id <= 0 ) {
			return null;
		}

		foreach ( self::all() as $domain => $entry ) {

			if ( (int) ( $entry['blog_id'] ?? 0 ) === $blog_id ) {
				return array( 'domain' => (string) $domain ) + $entry;
			}
		}

		return null;
	}

	/**
	 * Whether a domain is available to a given site.
	 *
	 * @param string $domain  Raw domain.
	 * @param int    $blog_id Site.
	 * @return bool
	 */
	public static function is_available( string $domain, int $blog_id ): bool {

		$result = self::validate( $domain, $blog_id );

		return '' === $result['error'];
	}

	/**
	 * A SITE may request its own custom domain (Phase 20 §9).
	 *
	 * The requester may only ever affect its OWN site: the blog id comes from the server,
	 * never from the browser. A domain already owned by another site is rejected (§41).
	 *
	 * @param int    $blog_id Site requesting (server-resolved).
	 * @param string $domain  Requested domain (raw).
	 * @param int    $user_id Requesting user.
	 * @return array{ok:bool,error:string,domain:string}
	 */
	public static function request( int $blog_id, string $domain, int $user_id = 0 ): array {

		$blog_id = absint( $blog_id );

		if ( $blog_id <= 0 ) {
			return array( 'ok' => false, 'error' => 'no_site', 'domain' => '' );
		}

		/* The caller must be allowed to administer THIS site. */
		if ( ! self::current_user_can_edit_site( $blog_id ) ) {
			return array( 'ok' => false, 'error' => 'forbidden', 'domain' => '' );
		}

		$checked = self::validate( $domain, $blog_id );

		if ( '' !== $checked['error'] ) {
			return array( 'ok' => false, 'error' => $checked['error'], 'domain' => $checked['domain'] );
		}

		$domain = $checked['domain'];

		$all = self::all();

		/*
		 * If the SAME site re-requests a domain it already holds, keep its original
		 * approval state instead of resetting it to pending.
		 */
		$previous = isset( $all[ $domain ] ) ? $all[ $domain ] : null;

		$all[ $domain ] = array(
			'blog_id'      => $blog_id,
			'status'       => $previous && self::STATUS_ACTIVE === ( $previous['status'] ?? '' )
				? self::STATUS_ACTIVE
				: self::STATUS_PENDING,
			'requested_by' => absint( $user_id ) > 0 ? absint( $user_id ) : get_current_user_id(),
			'requested_at' => $previous && isset( $previous['requested_at'] )
				? (int) $previous['requested_at']
				: time(),
			'updated_at'   => time(),
		);

		self::save( $all );

		return array( 'ok' => true, 'error' => '', 'domain' => $domain );
	}

	/**
	 * A SITE may remove its OWN custom domain (Phase 20 §10).
	 *
	 * Protected: the site's platform address (wp_blogs.domain) is never touched — only the
	 * custom-domain record is removed.
	 *
	 * @param int $blog_id Site (server-resolved).
	 * @return bool
	 */
	public static function remove_for_blog( int $blog_id ): bool {

		$blog_id = absint( $blog_id );

		if ( $blog_id <= 0 || ! self::current_user_can_edit_site( $blog_id ) ) {
			return false;
		}

		$all     = self::all();
		$changed = false;

		foreach ( $all as $domain => $entry ) {

			if ( (int) ( $entry['blog_id'] ?? 0 ) === $blog_id ) {
				unset( $all[ $domain ] );
				$changed = true;
			}
		}

		return $changed ? self::save( $all ) : false;
	}

	/**
	 * NETWORK authority: set a domain's state (approve / reject).
	 *
	 * @param string $domain Raw domain.
	 * @param string $status Target status.
	 * @return array{ok:bool,error:string}
	 */
	public static function set_status( string $domain, string $status ): array {

		if ( ! self::current_user_can_manage() ) {
			return array( 'ok' => false, 'error' => 'forbidden' );
		}

		$domain = self::normalize( $domain );

		if ( '' === $domain ) {
			return array( 'ok' => false, 'error' => 'invalid' );
		}

		$status = sanitize_key( $status );

		if ( ! in_array( $status, self::statuses(), true ) ) {
			return array( 'ok' => false, 'error' => 'invalid_status' );
		}

		$all = self::all();

		if ( ! isset( $all[ $domain ] ) ) {
			return array( 'ok' => false, 'error' => 'not_found' );
		}

		$all[ $domain ]['status']     = $status;
		$all[ $domain ]['updated_at'] = time();
		$all[ $domain ]['reviewed_by'] = get_current_user_id();

		self::save( $all );

		return array( 'ok' => true, 'error' => '' );
	}

	/**
	 * NETWORK authority: remove any domain from any site (Phase 20 §11).
	 *
	 * @param string $domain Raw domain.
	 * @return bool
	 */
	public static function remove( string $domain ): bool {

		if ( ! self::current_user_can_manage() ) {
			return false;
		}

		$domain = self::normalize( $domain );

		if ( '' === $domain ) {
			return false;
		}

		$all = self::all();

		if ( ! isset( $all[ $domain ] ) ) {
			return false;
		}

		unset( $all[ $domain ] );

		return self::save( $all );
	}

	/**
	 * Whether the current user may administer a specific site's domain.
	 *
	 * A site administrator may only ever affect their OWN site. On multisite, the membership
	 * check is done in that site's context so a user cannot act on a site they do not belong to.
	 *
	 * @param int $blog_id Target site.
	 * @return bool
	 */
	public static function current_user_can_edit_site( int $blog_id ): bool {

		$blog_id = absint( $blog_id );

		if ( $blog_id <= 0 ) {
			return false;
		}

		/* Network authority implies authority over any site. */
		if ( self::current_user_can_manage() ) {
			return true;
		}

		if ( ! is_multisite() ) {
			return current_user_can( 'manage_options' );
		}

		$switched = false;

		if ( $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$can = current_user_can( 'manage_options' );

		if ( $switched ) {
			restore_current_blog();
		}

		return (bool) $can;
	}

	/**
	 * All entries for one site.
	 *
	 * @param int $blog_id Site.
	 * @return array<string, array<string, mixed>>
	 */
	public static function for_blog( int $blog_id ): array {

		$blog_id = absint( $blog_id );
		$out     = array();

		foreach ( self::all() as $domain => $entry ) {

			if ( (int) ( $entry['blog_id'] ?? 0 ) === $blog_id ) {
				$out[ $domain ] = $entry;
			}
		}

		return $out;
	}
}