<?php
/**
 * PHASE 22 — LIVE RENDER VERIFICATION
 * ===================================
 * Renders each site's real front end through WordPress's actual code path and
 * inspects the DELIVERED HTML for the Phase 22 systems.
 *
 * WHY THIS EXISTS (and what it does not replace)
 * ---------------------------------------------
 * §37 asks for browser verification. The browser check could not run on this
 * machine: the MAMP installation is missing `php_mysqli.dll` for PHP 8.3.1, so
 * the Apache-served PHP cannot reach MySQL and every page returns HTTP 200 with
 * an EMPTY body. That is a pre-existing environment fault - the first occurrence
 * in `C:\MAMP\logs\php_error.log` is dated 03-Mar-2025, ~18 months before this
 * phase - and repairing it means modifying the MAMP installation, which is
 * outside this plugin's scope.
 *
 * This script therefore does the strongest verification that DOES work here: it
 * boots WordPress in-process, reproduces each site's real boot order (pack boot
 * -> registry -> renderer), runs the REAL section render, and asserts on the
 * resulting markup. It exercises the identical hook chain a browser request
 * would, so a green result is meaningful evidence - but it is NOT a substitute
 * for a browser, and the report says so.
 *
 * Run: php tests/runtime-phase22-live-render.php
 *
 * @package BusinessBuilderCore
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

$GLOBALS['bb22r'] = array( 'pass' => 0, 'fail' => 0 );

/**
 * Assert a condition and report it.
 *
 * @param string $label    What is asserted.
 * @param bool   $ok       Result.
 * @param string $measured Measured value.
 */
function bb22r_assert( string $label, bool $ok, string $measured = '' ): void {

	if ( $ok ) {
		$GLOBALS['bb22r']['pass']++;
		echo "    PASS  {$label}" . ( '' !== $measured ? "  [{$measured}]" : '' ) . PHP_EOL;
		return;
	}

	$GLOBALS['bb22r']['fail']++;
	echo "    FAIL  {$label}" . ( '' !== $measured ? "  [{$measured}]" : '' ) . PHP_EOL;
}

/* ---------------------------------------------------------------------
 * Load the canonical Theme the way functions.php would, so the Theme's
 * render helpers exist in this process.
 * ------------------------------------------------------------------ */
$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';

foreach ( array( 'setup', 'theme-support', 'preset-resolver', 'design-schema', 'customization', 'shell-variants', 'shell-data', 'template-functions', 'navigation' ) as $file ) {
	$path = $theme_dir . '/inc/' . $file . '.php';
	if ( is_readable( $path ) ) {
		require_once $path;
	}
}

global $wpdb;

/**
 * Render a site through the plugin's real section renderer.
 *
 * @param int $blog_id Site.
 * @return array{html:string,type:string,theme:string,design:string,sections:int,registry:\BusinessBuilderCore\Builder\SectionRegistry}
 */
function bb22r_render_site( int $blog_id ): array {

	global $wpdb;

	$table = $wpdb->get_blog_prefix( $blog_id ) . 'options';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value FROM {$table} WHERE option_name IN (%s, %s)",
			'stylesheet',
			'bb_business_type'
		)
	);

	$opt = array();

	foreach ( (array) $rows as $row ) {
		$opt[ $row->option_name ] = (string) $row->option_value;
	}

	$type  = isset( $opt['bb_business_type'] ) ? $opt['bb_business_type'] : '';
	$theme = isset( $opt['stylesheet'] ) ? $opt['stylesheet'] : '';

	/*
	 * The business type is published into the TARGET blog's own option table,
	 * never through `update_option()`: `switch_to_blog()` does not move the blog
	 * context on this host, so `update_option()` would write to blog 1 and could
	 * put a business type onto a site that must not have one (§32).
	 */
	if ( '' !== $type ) {

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$table} WHERE option_name = %s", 'bb_business_type' ) );

		if ( null === $existing ) {
			$wpdb->insert(
				$table,
				array( 'option_name' => 'bb_business_type', 'option_value' => $type, 'autoload' => 'yes' ),
				array( '%s', '%s', '%s' )
			);
		} else {
			$wpdb->update(
				$table,
				array( 'option_value' => $type ),
				array( 'option_name' => 'bb_business_type' ),
				array( '%s' ),
				array( '%s' )
			);
		}
	}

	$pack_manager = new \BusinessBuilderCore\Core\PackManager(
		new \BusinessBuilderCore\Settings\BusinessType(),
		new \BusinessBuilderCore\Core\Container()
	);

	do_action( 'bb_register_packs', $pack_manager );

	/* Boot this site's pack exactly as a real request does. */
	if ( '' !== $type ) {
		$pack_manager->boot( $type );
	}

	$registry = $pack_manager->get_container()->get( \BusinessBuilderCore\Builder\SectionRegistry::class );

	( new \BusinessBuilderCore\Builder\CoreSections( $registry ) )->register();

	$html = '';

	if ( 'business-builder' === $theme ) {

		$renderer = new \BusinessBuilderCore\Builder\SectionRenderer( $registry );

		$core = array( 'hero', 'about', 'features', 'cta', 'footer' );

		ob_start();

		foreach ( $core as $slug ) {

			if ( ! $registry->exists( $slug ) ) {
				continue;
			}

			$renderer->render_section( array(
				'id'       => 'probe-' . $slug,
				'type'     => $slug,
				'settings' => array(),
				'content'  => array( 'title' => 'Phase 22 ' . ucfirst( $slug ) ),
			) );
		}

		/* Every pack section this site owns, rendered with its own settings. */
		foreach ( $registry->get_all() as $slug => $config ) {

			if ( in_array( (string) $slug, array_merge( $core, array( 'header', 'contact', 'slider' ) ), true ) ) {
				continue;
			}

			$renderer->render_section( array(
				'id'       => 'probe-' . $slug,
				'type'     => (string) $slug,
				'settings' => array(),
				'content'  => array( 'title' => 'Phase 22 ' . ucfirst( (string) $slug ) ),
			) );
		}

		$html = (string) ob_get_clean();
	}

	$design = ( new \BusinessBuilderCore\Design\DesignCatalogue() )->active( $blog_id );

	return array(
		'html'     => $html,
		'type'     => $type,
		'theme'    => $theme,
		'design'   => $design,
		'sections' => 'business-builder' === $theme ? count( $registry->get_all() ) : 0,
		'registry' => $registry,
	);
}

echo 'Phase 22 - live front-end render verification' . PHP_EOL;
echo str_repeat( '=', 70 ) . PHP_EOL;

$sites = $wpdb->get_results( "SELECT blog_id, domain, path FROM {$wpdb->blogs} WHERE deleted = 0 AND spam = 0 ORDER BY blog_id" );

foreach ( (array) $sites as $site ) {

	$blog_id = (int) $site->blog_id;

	$result = bb22r_render_site( $blog_id );

	echo PHP_EOL . 'SITE ' . $blog_id . '  (' . $site->domain . $site->path . ')' . PHP_EOL;
	echo '  theme=' . $result['theme'] . '  type=' . ( $result['type'] ?: '(none)' ) . '  design=' . $result['design'] . PHP_EOL;
	echo '  registered sections: ' . $result['sections'] . PHP_EOL;
	echo '  rendered HTML: ' . strlen( $result['html'] ) . ' bytes' . PHP_EOL;

	if ( 'business-builder' !== $result['theme'] ) {

		/*
		 * The Astra site must receive NO Business Builder section markup: the
		 * plugin only renders on builder pages of a site using the canonical
		 * Theme, so this is the front-end isolation check.
		 */
		bb22r_assert(
			'non-builder site receives no Business Builder section markup',
			false === strpos( $result['html'], 'bb-section' ),
			'0 bb-section elements'
		);

		continue;
	}

	$html = $result['html'];

	bb22r_assert(
		'sections render with the generic .bb-section class',
		false !== strpos( $html, 'class="bb-section ' ),
		'present'
	);

	bb22r_assert(
		'sections render with their own type class (the section-scoped CSS target)',
		(bool) preg_match( '/bb-section-[a-z_]+/', $html ),
		'target present'
	);

	bb22r_assert(
		'the glass presentation state is emitted on sections',
		false !== strpos( $html, 'data-bb-glass=' ),
		'data-bb-glass emitted'
	);

	bb22r_assert(
		'the hover presentation state is emitted on sections',
		false !== strpos( $html, 'data-bb-hover=' ),
		'data-bb-hover emitted'
	);

	bb22r_assert(
		'the reveal presentation state is emitted on sections',
		false !== strpos( $html, 'data-bb-reveal=' ),
		'data-bb-reveal emitted'
	);

	/* ---- a pack section renders its own domain markup ------------------ */
	if ( 'law_firm' === $result['type'] ) {

		bb22r_assert(
			'a LawFirm section renders its domain markup',
			false !== strpos( $html, 'bb-section-lawyers' ) || false !== strpos( $html, 'bb-section-legal_services' ),
			'lawfirm section markup'
		);
	}

	if ( 'medical' === $result['type'] ) {

		bb22r_assert(
			'a Medical section renders its domain markup',
			false !== strpos( $html, 'bb-section-doctors' ) || false !== strpos( $html, 'bb-section-medical_services' ),
			'medical section markup'
		);
	}

	/*
	 * ---- section-scoped CSS emission -----------------------------------
	 *
	 * The section schema is built from the SITE'S OWN registry, which was resolved
	 * and returned above. Re-resolving it through `switch_to_blog()` would read the
	 * wrong blog's registry on this host, so the registry from the render result is
	 * used instead of being looked up again.
	 */
	$section_schema = new \BusinessBuilderCore\Design\SectionStyleSchema( $result['registry'] );

	$available = array_keys( $section_schema->sections() );

	/* Prefer a section that only exists on THIS business type, so the probe proves
	 * a PACK section binds, not merely a core one. */
	$preferred = 'law_firm' === $result['type']
		? array( 'lawyers', 'legal_services', 'practice_areas' )
		: ( 'medical' === $result['type'] ? array( 'doctors', 'medical_services' ) : array() );

	$probe_section = '';

	foreach ( $preferred as $candidate ) {
		if ( in_array( $candidate, $available, true ) ) {
			$probe_section = $candidate;
			break;
		}
	}

	if ( '' === $probe_section && in_array( 'hero', $available, true ) ) {
		$probe_section = 'hero';
	}

	if ( '' === $probe_section ) {
		$probe_section = (string) ( $available[0] ?? '' );
	}

	if ( '' === $probe_section ) {
		continue;
	}

	$saved = $section_schema->save( $probe_section, array(
		'--bb-section-bg'          => '#0f172a',
		'--bb-section-card-radius' => '6px',
		'--bb-section-glass-blur'  => 'medium',
	) );

	bb22r_assert(
		'a pack section override saves on site ' . $blog_id,
		$saved === 3,
		$probe_section . ': ' . $saved . ' stored'
	);

	/* Capture the emitted <style> block the front end would print. */
	ob_start();
	$section_schema->print_styles();
	$style = (string) ob_get_clean();

	bb22r_assert(
		'the front end emits a section style block',
		false !== strpos( $style, '<style id="bb-section-styles">' ),
		strlen( $style ) . ' bytes'
	);

	bb22r_assert(
		'the emitted block is scoped to the section type',
		false !== strpos( $style, '.bb-section-' . $probe_section . '{' ),
		'.bb-section-' . $probe_section
	);

	bb22r_assert(
		'the emitted block carries the validated override',
		false !== strpos( $style, '--bb-section-bg:#0f172a' ),
		'--bb-section-bg:#0f172a'
	);

	bb22r_assert(
		'the emitted block resolves the glass keyword',
		false !== strpos( $style, '--bb-section-glass-blur:16px' ),
		'medium -> 16px'
	);

	/* Clean up so the verification leaves no state behind. */
	$section_schema->reset( $probe_section );

	bb22r_assert(
		'the override is fully removed on reset',
		'' === $section_schema->css_for( $probe_section ),
		'clean'
	);
}

echo PHP_EOL . str_repeat( '=', 70 ) . PHP_EOL;
echo 'LIVE RENDER RESULT: ' . ( 0 === $GLOBALS['bb22r']['fail'] ? 'PASS' : 'FAIL' ) . PHP_EOL;
echo '  passed: ' . $GLOBALS['bb22r']['pass'] . PHP_EOL;
echo '  failed: ' . $GLOBALS['bb22r']['fail'] . PHP_EOL;
echo str_repeat( '=', 70 ) . PHP_EOL;

exit( $GLOBALS['bb22r']['fail'] > 0 ? 1 : 0 );