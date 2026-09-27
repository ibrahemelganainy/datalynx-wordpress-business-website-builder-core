<?php

namespace BusinessBuilderCore\Network;

use BusinessBuilderCore\Core\PackManager;
use BusinessBuilderCore\Settings\BusinessType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site provisioning (Phase 20 §5, §14, §15, §22, §25, §26).
 *
 * Creates a REAL WordPress Multisite site via `wpmu_create_blog()` — not an option, not a
 * custom row (§14). Everything is validated BEFORE creation so the realistic failure causes
 * are eliminated up front (§22).
 *
 * REGISTRY-DRIVEN, NEVER BRANCHED (§4, §29):
 *   - business types come from BusinessType::get_all()
 *   - the pack is resolved through PackManager::exists() / get()
 *   - presets come from the Theme's own registry
 * There is NO `if ( 'law_firm' )` / `if ( 'medical' )` anywhere in this class. A future pack
 * becomes selectable the moment it self-registers.
 *
 * This class knows NOTHING about sections (§26) or pack business data (§25): it creates the
 * platform site and assigns its identity. The pack owns its domain data.
 */
class SiteProvisioner {

	/**
	 * Network option recording provisioning outcomes (audit + failure policy, §22).
	 */
	public const OUTCOME_OPTION = 'bb_network_provisioning_log';

	/**
	 * The capability required to create sites.
	 *
	 * @return string
	 */
	public static function required_capability(): string {

		return 'manage_sites';
	}

	/**
	 * Whether the current user may provision sites.
	 *
	 * @return bool
	 */
	public static function current_user_can_provision(): bool {

		if ( ! is_multisite() ) {
			return false;
		}

		if ( is_super_admin() ) {
			return true;
		}

		return current_user_can( self::required_capability() ) && current_user_can( 'manage_network' );
	}

	/**
	 * The business types a network administrator may choose, straight from the registry.
	 *
	 * @param BusinessType $business_type Registry.
	 * @return array<string, array<string, mixed>>
	 */
	public static function available_business_types( BusinessType $business_type ): array {

		return $business_type->get_all();
	}

	/**
	 * Resolve the Pack for a business type through the EXISTING Pack architecture.
	 *
	 * @param PackManager $pack_manager  Pack registry.
	 * @param string      $business_type Business type slug.
	 * @return array{slug:string,class:string,available:bool}
	 */
	public static function resolve_pack( PackManager $pack_manager, string $business_type ): array {

		$slug = sanitize_key( $business_type );

		$class = $pack_manager->get( $slug );

		return array(
			'slug'      => $slug,
			'class'     => (string) $class,
			'available' => $pack_manager->exists( $slug ),
		);
	}

	/**
	 * The design presets the Network may assign, discovered from the Theme registry.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function available_presets(): array {

		$options = array();

		if ( ! function_exists( 'bb_theme_presets' ) ) {
			return $options;
		}

		foreach ( bb_theme_presets() as $slug => $preset ) {

			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			$options[ $slug ] = isset( $preset['label'] )
				? (string) $preset['label']
				: $slug;
		}

		return $options;
	}

	/**
	 * Validate a preset slug against the Theme's own registry.
	 *
	 * Uses the Theme's validator where available so an unregistered value can never be written
	 * (Phase 20 §7/§8).
	 *
	 * @param string $preset Preset slug.
	 * @return bool
	 */
	public static function is_valid_preset( string $preset ): bool {

		$preset = sanitize_key( $preset );

		if ( '' === $preset ) {
			return false;
		}

		$available = self::available_presets();

		/*
		 * The Theme owns the preset registry and the Theme is always loaded in a real
		 * admin request, so an empty registry here means the registry is unavailable
		 * (e.g. a bootstrap/CLI context), NOT that the preset is invalid. Accepting only
		 * the Theme's guaranteed default in that case keeps the value safe while never
		 * letting an unregistered slug through when the registry IS loaded.
		 */
		if ( empty( $available ) ) {
			return 'default' === $preset;
		}

		return array_key_exists( $preset, $available );
	}

	/**
	 * Normalize a requested subdomain/path into a valid site address.
	 *
	 * @param string $address Raw address (e.g. "NewClinic").
	 * @return string Sanitized slug, or '' when unusable.
	 */
	public static function normalize_address( string $address ): string {

		$address = strtolower( trim( $address ) );

		/*
		 * Accept a full URL and keep only the first label. '~' is the delimiter because the
		 * patterns contain '[' .. ']' classes and a literal '#' inside the character class.
		 */
		$address = preg_replace( '~^[a-z][a-z0-9+.\-]*://~i', '', $address );
		$address = preg_replace( '~[:/?#].*$~', '', (string) $address );

		$address = sanitize_key( str_replace( '.', '', (string) $address ) );

		if ( strlen( $address ) < 2 || strlen( $address ) > 60 ) {
			return '';
		}

		if ( ! preg_match( '/^[a-z0-9][a-z0-9\-]*$/', $address ) ) {
			return '';
		}

		return $address;
	}

	/**
	 * Whether a subdomain is already taken (or reserved).
	 *
	 * @param string $address Normalized address.
	 * @return string '' when free, else an error code.
	 */
	public static function check_address( string $address ): string {

		if ( '' === $address ) {
			return 'invalid';
		}

		$base = DomainRegistry::platform_base_domain();

		/*
		 * A customer site lives at <address>.<base>. That is NOT a reserved host — the
		 * whole point of the network is to hand out such addresses. Only the PLATFORM
		 * subdomains (www, admin, network, ...) plus the base domain itself are protected.
		 */
		if ( in_array( $address, DomainRegistry::reserved_labels(), true ) ) {
			return 'reserved';
		}

		if ( '' === $base ) {
			return 'invalid';
		}

		$domain = $address . '.' . $base;

		if ( is_multisite() && function_exists( 'domain_exists' ) && domain_exists( $domain, '/' ) ) {
			return 'taken';
		}

		return '';
	}

	/**
	 * Fully validate a provisioning request WITHOUT creating anything.
	 *
	 * @param array<string, mixed> $request Raw request.
	 * @param BusinessType         $business_type Registry.
	 * @param PackManager          $pack_manager  Pack registry.
	 * @return array{ok:bool,errors:array<string,string>,data:array<string,mixed>}
	 */
	public static function validate( array $request, BusinessType $business_type, PackManager $pack_manager ): array {

		$errors = array();

		$title   = isset( $request['title'] ) ? sanitize_text_field( (string) $request['title'] ) : '';
		$address = isset( $request['address'] ) ? self::normalize_address( (string) $request['address'] ) : '';
		$type    = isset( $request['business_type'] ) ? sanitize_key( (string) $request['business_type'] ) : '';
		$preset  = isset( $request['preset'] ) ? sanitize_key( (string) $request['preset'] ) : '';
		$domain  = isset( $request['custom_domain'] ) ? (string) $request['custom_domain'] : '';

		if ( '' === $title ) {
			$errors['title'] = 'required';
		}

		if ( '' === $address ) {
			$errors['address'] = 'invalid';
		} else {
			$address_error = self::check_address( $address );

			if ( '' !== $address_error ) {
				$errors['address'] = $address_error;
			}
		}

		/* Business Type must come from the authoritative registry. */
		if ( '' === $type || ! $business_type->exists( $type ) ) {
			$errors['business_type'] = 'invalid';
		}

		/* The pack must exist for the chosen type (registry-resolved, never hardcoded). */
		if ( ! isset( $errors['business_type'] ) ) {

			$pack = self::resolve_pack( $pack_manager, $type );

			if ( ! $pack['available'] ) {
				$errors['pack'] = 'missing';
			}
		}

		/* The preset must be a registered Theme preset. */
		if ( '' !== $preset && ! self::is_valid_preset( $preset ) ) {
			$errors['preset'] = 'invalid';
		}

		/* A custom domain is optional at provisioning time, but must be valid if supplied. */
		if ( '' !== trim( $domain ) ) {

			$normalized = DomainRegistry::normalize( $domain );

			if ( '' === $normalized ) {
				$errors['custom_domain'] = 'invalid';
			} elseif ( DomainRegistry::is_reserved( $normalized ) ) {
				$errors['custom_domain'] = 'reserved';
			} elseif ( null !== DomainRegistry::find( $normalized ) ) {
				$errors['custom_domain'] = 'duplicate';
			} elseif ( DomainRegistry::is_platform_host( $normalized ) ) {
				$errors['custom_domain'] = 'platform';
			}
		}

		return array(
			'ok'     => empty( $errors ),
			'errors' => $errors,
			'data'   => array(
				'title'         => $title,
				'address'       => $address,
				'business_type' => $type,
				'preset'        => $preset,
				'custom_domain' => $domain,
			),
		);
	}

	/**
	 * Provision a site.
	 *
	 * @param array<string, mixed> $request       Raw request.
	 * @param BusinessType         $business_type Registry.
	 * @param PackManager          $pack_manager  Pack registry.
	 * @return array{ok:bool,blog_id:int,errors:array<string,string>,warnings:string[]}
	 */
	public static function provision( array $request, BusinessType $business_type, PackManager $pack_manager ): array {

		if ( ! self::current_user_can_provision() ) {
			return array( 'ok' => false, 'blog_id' => 0, 'errors' => array( 'auth' => 'forbidden' ), 'warnings' => array() );
		}

		$checked = self::validate( $request, $business_type, $pack_manager );

		if ( ! $checked['ok'] ) {
			return array( 'ok' => false, 'blog_id' => 0, 'errors' => $checked['errors'], 'warnings' => array() );
		}

		$data = $checked['data'];

		$base   = DomainRegistry::platform_base_domain();
		$domain = $data['address'] . '.' . $base;

		/*
		 * A network administrator provisions sites for a customer; the creator is recorded as
		 * the administrator of the new site unless a user id was supplied.
		 */
		$admin_id = isset( $request['admin_id'] ) ? absint( $request['admin_id'] ) : get_current_user_id();

		$blog_id = wpmu_create_blog(
			$domain,
			'/',
			$data['title'],
			$admin_id,
			array( 'public' => 1 ),
			get_current_network_id()
		);

		if ( is_wp_error( $blog_id ) ) {
			return array(
				'ok'      => false,
				'blog_id' => 0,
				'errors'  => array( 'create' => $blog_id->get_error_code() ),
				'warnings' => array(),
			);
		}

		$blog_id = (int) $blog_id;

		/*
		 * Everything below runs INSIDE the new site's context. Phase 19 produced a real
		 * cross-site contamination defect by writing outside switch_to_blog(); this method
		 * therefore keeps every site-local write in one explicit, restored scope (§21).
		 */
		$warnings = array();

		switch_to_blog( $blog_id );

		try {

			/* 1. Business Builder Theme where the platform requires it. */
			if ( 'business-builder' !== (string) get_option( 'stylesheet' ) ) {
				switch_theme( 'business-builder' );
			}

			if ( 'business-builder' !== (string) get_option( 'stylesheet' ) ) {
				$warnings[] = 'theme';
			}

			/* 2. Business Type (authoritative, registry-validated). */
			$assigned = $business_type->set_current( $data['business_type'] );

			if ( ! $assigned ) {
				$warnings[] = 'business_type';
			}

			/* 3. Initial design preset (existing Theme mod; never a new field). */
			if ( '' !== $data['preset'] ) {

				/*
				 * The registry is validated BEFORE creation (SiteProvisioner::validate()), so a
				 * value reaching this point is either registered or the Theme's guaranteed
				 * default. When the Theme's registry is unavailable in this process, only the
				 * default may be written — never an unverified slug.
				 */
				$available = function_exists( 'bb_theme_presets' ) ? bb_theme_presets() : array();

				if ( empty( $available ) || isset( $available[ $data['preset'] ] ) ) {
					set_theme_mod( 'bb_theme_preset', $data['preset'] );
				} else {
					$warnings[] = 'preset';
				}
			}

			/* 4. Minimal, honest site initialisation. */
			update_option( 'blog_public', 1 );

			if ( ! get_option( 'permalink_structure' ) ) {
				update_option( 'permalink_structure', '/%postname%/' );
			}

		} finally {

			restore_current_blog();
		}

		/* Network-scoped record of the most recently provisioned site (outside the switch). */
		update_site_option( 'bb_network_last_provisioned_blog', $blog_id );

		/*
		 * 5. Custom domain — recorded in the NETWORK registry, NOT written into wp_blogs.
		 *    It belongs to the site, so the association is explicit and network-scoped.
		 */
		if ( '' !== trim( (string) $data['custom_domain'] ) ) {

			$all      = DomainRegistry::all();
			$normalized = DomainRegistry::normalize( (string) $data['custom_domain'] );

			if ( '' !== $normalized ) {
				$all[ $normalized ] = array(
					'blog_id'      => $blog_id,
					'status'       => DomainRegistry::STATUS_PENDING,
					'requested_by' => get_current_user_id(),
					'requested_at' => time(),
					'updated_at'   => time(),
					'source'       => 'provisioning',
				);

				update_site_option( DomainRegistry::OPTION, $all );
			}
		}

		self::record_outcome( $blog_id, $data, $warnings );

		return array(
			'ok'       => empty( $warnings ),
			'blog_id'  => $blog_id,
			'errors'   => array(),
			'warnings' => $warnings,
		);
	}

	/**
	 * Record the provisioning outcome for the Network list (failure policy, §22).
	 *
	 * Nothing is auto-deleted: a half-configured site is surfaced as needing attention so the
	 * operator can finish or remove it deliberately.
	 *
	 * @param int                   $blog_id  New site.
	 * @param array<string, mixed>  $data     Validated data.
	 * @param string[]              $warnings Non-fatal step failures.
	 */
	private static function record_outcome( int $blog_id, array $data, array $warnings ): void {

		$log = get_site_option( self::OUTCOME_OPTION, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[ (string) $blog_id ] = array(
			'title'         => (string) $data['title'],
			'address'       => (string) $data['address'],
			'business_type' => (string) $data['business_type'],
			'preset'        => (string) $data['preset'],
			'status'        => empty( $warnings ) ? 'complete' : 'needs_attention',
			'warnings'      => array_values( $warnings ),
			'created_by'    => get_current_user_id(),
			'created_at'    => time(),
		);

		update_site_option( self::OUTCOME_OPTION, $log );
	}

	/**
	 * The provisioning log.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function outcomes(): array {

		$log = get_site_option( self::OUTCOME_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Outcome for one site.
	 *
	 * @param int $blog_id Site.
	 * @return array<string, mixed>|null
	 */
	public static function outcome_for( int $blog_id ): ?array {

		$log = self::outcomes();

		return isset( $log[ (string) $blog_id ] ) ? $log[ (string) $blog_id ] : null;
	}
}