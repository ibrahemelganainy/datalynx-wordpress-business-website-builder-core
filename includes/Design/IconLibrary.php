<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon Library (§18) — Font Awesome Free.
 *
 * ONE icon vocabulary for the whole platform, with ONE renderer and ONE
 * validation rule, so a section, a card, a button and a Studio preview all
 * resolve an icon the same way.
 *
 * WHY A VOCABULARY AND NOT A FIELD
 * --------------------------------
 * An icon is stored as a SLUG (`briefcase`), never as markup, a class string or a
 * URL. So a forged value can never become HTML (the slug is looked up in a closed
 * registry and rendered from that registry's own data), and the stored value is
 * provider-independent: re-pointing the integration is a rendering decision, not a
 * data migration.
 *
 * FONT AWESOME INTEGRATION — THE MEASURED DECISIONS (§18)
 * ------------------------------------------------------
 * The requirement is a clean integration that does NOT double-load Font Awesome
 * and does NOT ship the whole library for one icon. Both are honoured:
 *
 *   1. FONT AWESOME IS LOADED ONLY WHEN AN ICON ACTUALLY RENDERS. The decision is
 *      made on `wp_enqueue_scripts` at priority 30 — after the content exists —
 *      from a flag the renderer sets. A page with no icons makes no Font Awesome
 *      request at all.
 *   2. IF FONT AWESOME IS ALREADY ON THE SITE IT IS REUSED. The known handles
 *      (theme, Elementor, another plugin) are checked first, so a second copy is
 *      never enqueued. That is what "prevent duplicate loading" means in practice,
 *      and the Phase 23 suite asserts it.
 *   3. THE URL IS FILTERABLE (`bb_icon_fontawesome_url`) and version-pinned, so a
 *      site can self-host and traffic no third party.
 *
 * The catalogue is grouped by SEMANTIC category (never by business type), so this
 * file stays in the generic layer. A pack extends it with `bb_icon_catalogue`.
 */
class IconLibrary {

	/**
	 * The stylesheet handle this plugin uses when IT has to load Font Awesome.
	 */
	public const FA_HANDLE = 'bb-fontawesome';

	/**
	 * Font Awesome Free, version pinned.
	 */
	public const FA_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';

	/**
	 * Handles that indicate Font Awesome is ALREADY on the site.
	 *
	 * @var string[]
	 */
	protected const FA_KNOWN_HANDLES = array(
		'font-awesome',
		'fontawesome',
		'font-awesome-5',
		'fontawesome-5',
		'fa',
		'fa-5',
		'fa6',
		'elementor-icons-fa-regular',
		'elementor-icons-fa-solid',
		'elementor-icons-fa-brands',
	);

	/**
	 * Whether an icon was rendered during this request.
	 *
	 * STATIC ON PURPOSE: the renderer called from a template and the instance that
	 * owns the `wp_enqueue_scripts` hook need not be the same object (the
	 * procedural `bb_render_icon()` helper lazily builds its own). The fact being
	 * tracked — "did this REQUEST draw an icon" — belongs to the request, not to an
	 * object. It is request-scoped like every other PHP value, so it cannot leak.
	 */
	protected static bool $needed = false;

	/**
	 * Whether the asset pass has already run (at most one enqueue per request).
	 */
	protected static bool $asset_done = false;

	/**
	 * Register the hooks.
	 */
	public function register(): void {

		/*
		 * Priority 30 is deliberately LATE: the decision must be made after the page
		 * content has been rendered, so it is based on what was actually drawn
		 * rather than on a guess made before the content exists.
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 30 );
	}

	/* =====================================================================
	 * 1. The catalogue
	 * ================================================================== */

	/**
	 * The icon catalogue: category => [ slug => Font Awesome icon name ].
	 *
	 * The VALUE is the Font Awesome Free 6 icon name; the KEY is the slug that is
	 * stored. Keeping them apart means an icon-set change is a one-line edit here
	 * instead of a database migration.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function catalogue(): array {

		$catalogue = array(

			'general'       => array(
				'check'        => 'check',
				'check-circle' => 'circle-check',
				'info'         => 'circle-info',
				'warning'      => 'triangle-exclamation',
				'star'         => 'star',
				'heart'        => 'heart',
				'bolt'         => 'bolt',
				'award'        => 'award',
				'trophy'       => 'trophy',
				'gift'         => 'gift',
				'thumbs-up'    => 'thumbs-up',
				'sparkles'     => 'wand-magic-sparkles',
			),

			'business'      => array(
				'briefcase'    => 'briefcase',
				'building'     => 'building',
				'city'         => 'city',
				'industry'     => 'industry',
				'warehouse'    => 'warehouse',
				'chart-line'   => 'chart-line',
				'chart-bar'    => 'chart-column',
				'chart-pie'    => 'chart-pie',
				'lightbulb'    => 'lightbulb',
				'rocket'       => 'rocket',
				'bullseye'     => 'bullseye',
				'handshake'    => 'handshake',
				'clipboard'    => 'clipboard-check',
				'calculator'   => 'calculator',
			),

			'finance'       => array(
				'wallet'       => 'wallet',
				'credit-card'  => 'credit-card',
				'money'        => 'money-bill-wave',
				'coins'        => 'coins',
				'piggy'        => 'piggy-bank',
				'receipt'      => 'receipt',
				'invoice'      => 'file-invoice-dollar',
				'scale'        => 'scale-balanced',
				'shield'       => 'shield-halved',
				'lock'         => 'lock',
				'key'          => 'key',
				'fingerprint'  => 'fingerprint',
				'id-card'      => 'id-card',
			),

			'communication' => array(
				'phone'        => 'phone',
				'envelope'     => 'envelope',
				'chat'         => 'comments',
				'comment'      => 'comment-dots',
				'paper-plane'  => 'paper-plane',
				'megaphone'    => 'bullhorn',
				'bell'         => 'bell',
				'globe'        => 'globe',
				'share'        => 'share-nodes',
				'link'         => 'link',
				'mobile'       => 'mobile-screen-button',
				'headset'      => 'headset',
			),

			'people'        => array(
				'user'          => 'user',
				'advisor'       => 'user-doctor',
				'graduate'      => 'user-graduate',
				'guardian'      => 'user-shield',
				'users'         => 'users',
				'team'          => 'people-group',
				'accessibility' => 'universal-access',
			),

			'health'        => array(
				'heart-pulse'  => 'heart-pulse',
				'stethoscope'  => 'stethoscope',
				'pills'        => 'pills',
				'syringe'      => 'syringe',
				'microscope'   => 'microscope',
				'flask'        => 'flask-vial',
				'dna'          => 'dna',
				'tooth'        => 'tooth',
				'brain'        => 'brain',
				'hospital'     => 'hospital',
			),

			'justice'       => array(
				'gavel'        => 'gavel',
				'book-law'     => 'book-open',
				'stamp'        => 'stamp',
				'columns'      => 'building-columns',
				'signature'    => 'file-signature',
				'certificate'  => 'certificate',
			),

			'education'     => array(
				'graduation'   => 'graduation-cap',
				'book'         => 'book',
				'library'      => 'book-bookmark',
				'pen'          => 'pen',
				'chalkboard'   => 'chalkboard-user',
				'atom'         => 'atom',
				'school'       => 'school',
			),

			'home'          => array(
				'house'        => 'house',
				'house-alt'    => 'house-chimney',
				'bed'          => 'bed',
				'sofa'         => 'couch',
				'chair'        => 'chair',
				'door'         => 'door-open',
				'blueprint'    => 'compass-drafting',
				'ruler'        => 'ruler-combined',
				'map-location' => 'map-location-dot',
			),

			'technology'    => array(
				'code'         => 'code',
				'laptop'       => 'laptop-code',
				'database'     => 'database',
				'cloud'        => 'cloud',
				'server'       => 'server',
				'cog'          => 'gear',
				'wrench'       => 'wrench',
				'robot'        => 'robot',
				'camera'       => 'camera',
				'video'        => 'video',
				'image'        => 'image',
				'palette'      => 'palette',
			),

			'logistics'     => array(
				'truck'        => 'truck-fast',
				'van'          => 'van-shuttle',
				'car'          => 'car',
				'plane'        => 'plane',
				'ship'         => 'ship',
				'cart'         => 'cart-shopping',
				'package'      => 'boxes-stacked',
				'route'        => 'route',
			),

			'nature'        => array(
				'leaf'         => 'leaf',
				'seedling'     => 'seedling',
				'tree'         => 'tree',
				'water'        => 'droplet',
				'sun'          => 'sun',
				'moon'         => 'moon',
				'fire'         => 'fire-flame-curved',
				'wind'         => 'wind',
				'recycle'      => 'recycle',
				'earth'        => 'earth-americas',
			),

			'time'          => array(
				'clock'          => 'clock',
				'calendar'       => 'calendar-days',
				'calendar-check' => 'calendar-check',
				'hourglass'      => 'hourglass-half',
				'history'        => 'clock-rotate-left',
			),

			'ui'            => array(
				'search'       => 'magnifying-glass',
				'filter'       => 'filter',
				'sliders'      => 'sliders',
				'table'        => 'table-cells',
				'list'         => 'list',
				'grid'         => 'grip',
				'expand'       => 'up-right-and-down-left-from-center',
				'download'     => 'download',
				'upload'       => 'upload',
				'external'     => 'arrow-up-right-from-square',
				'quote'        => 'quote-left',
				'plus'         => 'circle-plus',
			),
		);

		/**
		 * Filter the icon catalogue.
		 *
		 * A pack adds its own icons (or a whole category) without touching this class,
		 * and without the generic layer learning a business type.
		 *
		 * @param array<string, array<string, string>> $catalogue category => slug => Font Awesome name.
		 */
		$catalogue = apply_filters( 'bb_icon_catalogue', $catalogue );

		return is_array( $catalogue ) ? $catalogue : array();
	}

	/**
	 * Flat list of every valid slug.
	 *
	 * @return string[]
	 */
	public function slugs(): array {

		$slugs = array();

		foreach ( $this->catalogue() as $icons ) {

			foreach ( array_keys( (array) $icons ) as $slug ) {
				$slugs[ (string) $slug ] = true;
			}
		}

		return array_keys( $slugs );
	}

	/**
	 * A FLAT option map (slug => "Category — Label") for a plain `<select>`.
	 *
	 * Shaped for the builder's EXISTING `select` field type, so an icon control
	 * works in the page editor today with no new field type and no JavaScript.
	 *
	 * @return array<string, string>
	 */
	public function flat_options(): array {

		$flat = array();

		foreach ( $this->catalogue() as $category => $icons ) {

			foreach ( array_keys( (array) $icons ) as $slug ) {

				$flat[ (string) $slug ] = ucfirst( (string) $category ) . ' — '
					. ucwords( str_replace( '-', ' ', (string) $slug ) );
			}
		}

		return $flat;
	}

	/**
	 * Font Awesome name for every slug: [ slug => name ].
	 *
	 * The admin picker renders a live preview of each option, and the preview needs
	 * the Font Awesome NAME (a slug such as `chart-bar` maps to `chart-column`).
	 * Exposing the map keeps that translation in ONE place — the same place the
	 * frontend renderer uses — instead of duplicating a second table in JavaScript.
	 *
	 * @return array<string, string>
	 */
	public function names(): array {

		$names = array();

		foreach ( $this->catalogue() as $icons ) {

			foreach ( (array) $icons as $slug => $name ) {
				$names[ (string) $slug ] = (string) $name;
			}
		}

		return $names;
	}

	/* =====================================================================
	 * 2. Validation
	 * ================================================================== */

	/**
	 * Validate a stored icon value.
	 *
	 * Returns '' for anything that is not a known slug, so an unknown, forged or
	 * partial value renders NOTHING rather than arbitrary markup. This is the ONE
	 * validation rule for every icon in the platform.
	 *
	 * @param mixed $value Raw value.
	 * @return string A known slug, or ''.
	 */
	public function validate( $value ): string {

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$slug = sanitize_key( (string) $value );

		return in_array( $slug, $this->slugs(), true ) ? $slug : '';
	}

	/* =====================================================================
	 * 3. Rendering
	 * ================================================================== */

	/**
	 * Render an icon.
	 *
	 * @param mixed                $value Raw stored value (a slug).
	 * @param array<string, mixed> $args  Optional `label` (accessible name) and `class`.
	 * @return string Safe HTML, or '' when the value is not a known icon.
	 */
	public function render( $value, array $args = array() ): string {

		$slug = $this->validate( $value );

		if ( '' === $slug ) {
			return '';
		}

		$name = $this->name_for( $slug );

		$label = isset( $args['label'] ) ? (string) $args['label'] : '';
		$class = isset( $args['class'] ) ? (string) $args['class'] : '';

		$class = trim( 'bb-icon bb-icon-' . $slug . ' fa-solid fa-' . $name . ' ' . $class );

		/*
		 * From here on a Font Awesome stylesheet is genuinely required, which is
		 * what makes the enqueue conditional instead of unconditional.
		 */
		self::$needed = true;

		$html = '<i class="' . esc_attr( $class ) . '"';

		if ( '' === $label ) {
			$html .= ' aria-hidden="true"';
		} else {
			$html .= ' role="img" aria-label="' . esc_attr( $label ) . '"';
		}

		return $html . '></i>';
	}

	/**
	 * The Font Awesome icon name for a slug.
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public function name_for( string $slug ): string {

		foreach ( $this->catalogue() as $icons ) {

			if ( isset( $icons[ $slug ] ) ) {
				return (string) $icons[ $slug ];
			}
		}

		return $slug;
	}

	/**
	 * The category a slug belongs to ('general' when unknown).
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public function category_for( string $slug ): string {

		foreach ( $this->catalogue() as $category => $icons ) {

			if ( isset( $icons[ $slug ] ) ) {
				return (string) $category;
			}
		}

		return 'general';
	}

	/* =====================================================================
	 * 4. Assets (conditional AND de-duplicated)
	 * ================================================================== */

	/**
	 * Load the icon font, but only when it is genuinely needed, and only once.
	 *
	 * ORDER OF DECISIONS (§18)
	 * ------------------------
	 *  1. Did anything render an icon this request? If not, stop — no request, no
	 *     bytes, no third-party call.
	 *  2. Is Font Awesome ALREADY on this site? If yes, reuse it and stop. This is
	 *     the "prevent duplicate loading" guarantee.
	 *  3. Otherwise load it ONCE, from a filterable URL (so it can be self-hosted),
	 *     pinned to a version.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {

		if ( self::$asset_done ) {
			return;
		}

		self::$asset_done = true;

		if ( ! self::$needed ) {
			return;
		}

		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		if ( $this->fontawesome_already_loaded() ) {
			return;
		}

		/**
		 * Filter the Font Awesome stylesheet URL.
		 *
		 * Point this at a self-hosted copy to avoid any third-party request.
		 *
		 * @param string $url Stylesheet URL.
		 */
		$url = (string) apply_filters( 'bb_icon_fontawesome_url', self::FA_CDN );

		if ( '' === trim( $url ) ) {
			return;
		}

		wp_enqueue_style( self::FA_HANDLE, esc_url_raw( $url ), array(), '6.5.2' );
	}

	/**
	 * Whether Font Awesome is already registered or enqueued on this site.
	 *
	 * @return bool
	 */
	public function fontawesome_already_loaded(): bool {

		if ( ! function_exists( 'wp_style_is' ) ) {
			return false;
		}

		$handles = (array) apply_filters( 'bb_icon_fontawesome_handles', self::FA_KNOWN_HANDLES );

		foreach ( $handles as $handle ) {

			$handle = (string) $handle;

			if ( '' !== $handle
				&& ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an icon was rendered during this request.
	 *
	 * Exposed so the "loads only when needed" claim can be asserted, not assumed.
	 *
	 * @return bool
	 */
	public function was_needed(): bool {

		return self::$needed;
	}

	/**
	 * Load the Font Awesome stylesheet for an ADMIN screen that previews icons.
	 *
	 * The admin icon picker draws a live glyph per option, so on THAT screen the
	 * font is genuinely required even though nothing has rendered an icon through
	 * the frontend renderer. The request flag is set here for exactly that reason,
	 * and the SAME `maybe_enqueue_assets()` pass is used, so the de-duplication
	 * rules (reuse an existing Font Awesome, never enqueue twice) still apply.
	 *
	 * @return void
	 */
	public function enqueue_for_admin(): void {

		self::$needed = true;

		$this->maybe_enqueue_assets();
	}

	/**
	 * Reset the request-scoped flags (tests only).
	 *
	 * @return void
	 */
	public function reset_state(): void {

		self::$needed     = false;
		self::$asset_done = false;
	}
}

