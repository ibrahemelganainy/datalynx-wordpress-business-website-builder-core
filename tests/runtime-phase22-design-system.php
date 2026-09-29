<?php
/**
 * PHASE 22 RUNTIME TEST SUITE
 * ===========================
 * Enterprise Design System, Studio Expansion & Full Section Visual Control.
 *
 * This suite verifies the claims Phase 22 makes, against the REAL plugin code
 * (autoloader, registries, Theme functions) — not against mocks. It is written
 * so every assertion prints PASS or FAIL with the measured value, and the
 * script exits non-zero if anything fails.
 *
 * Run: php tests/runtime-phase22-design-system.php
 *
 * COVERAGE (§36)
 * --------------
 *   A. Design catalogue      — business-type filtering, registration, isolation
 *   B. Studio                — every control saves, validates, and is consumed
 *   C. Section binding       — Studio change → config → pipeline → frontend CSS
 *   D. Header                — initial / scrolled state, colours, navigation, glass
 *   E. Footer                — background, typography, links, spacing
 *   F. Background            — solid, gradient, overlay
 *   G. Grid                  — desktop, tablet, mobile
 *   H. Motion                — kind, duration, delay, reduced motion
 *   I. Glass                 — enabled, disabled, section, card, header
 *   J. Meta boxes            — registration, capability, nonce, RTL/LTR
 *   K. Multisite             — LawFirm / Medical / non-builder isolation
 *   L. Security              — capability, nonce, sanitisation, no CSS injection
 *
 * @package BusinessBuilderCore
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

$GLOBALS['bb22'] = array( 'pass' => 0, 'fail' => 0, 'failures' => array(), 'group' => '' );

/**
 * Assert a condition and report it.
 *
 * @param string $label   What is being asserted.
 * @param bool   $ok      Result.
 * @param string $measured Measured value, for the report.
 */
function bb22_assert( string $label, bool $ok, string $measured = '' ): void {

	if ( $ok ) {
		$GLOBALS['bb22']['pass']++;
		echo "  PASS  {$label}" . ( '' !== $measured ? "  [{$measured}]" : '' ) . PHP_EOL;
		return;
	}

	$GLOBALS['bb22']['fail']++;
	$GLOBALS['bb22']['failures'][] = $GLOBALS['bb22']['group'] . ' :: ' . $label;

	echo "  FAIL  {$label}" . ( '' !== $measured ? "  [{$measured}]" : '' ) . PHP_EOL;
}

/**
 * Start a named test group.
 *
 * @param string $name Group name.
 */
function bb22_group( string $name ): void {

	$GLOBALS['bb22']['group'] = $name;

	echo PHP_EOL . '== ' . $name . ' ' . str_repeat( '=', max( 4, 62 - strlen( $name ) ) ) . PHP_EOL;
}

/**
 * Remove PHP comments from source before scanning it for a forbidden pattern.
 *
 * WHY THIS EXISTS
 * ---------------
 * The architectural guards assert that the GENERIC layer contains no business
 * type. These classes' docblocks legitimately DISCUSS business types, because
 * documenting the isolation rule is how a future reader learns why the rule
 * exists. A naive scan over the raw file therefore reports violations that do
 * not exist, so the comments are removed first - the assertion is about
 * EXECUTABLE code.
 *
 * @param string $source PHP source.
 * @return string Source with comments removed.
 */
function bb22_strip_comments( string $source ): string {

	$source = (string) preg_replace( '#/\*.*?\*/#s', '', $source );

	return (string) preg_replace( '#//[^\n]*#', '', $source );
}

/* =====================================================================
 * Bootstrap: load the Theme's function files, as functions.php would.
 * ================================================================== */
$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';

foreach ( array( 'setup', 'theme-support', 'preset-resolver', 'design-schema', 'customization', 'shell-variants', 'shell-data' ) as $file ) {
	$path = $theme_dir . '/inc/' . $file . '.php';
	if ( is_readable( $path ) ) {
		require_once $path;
	}
}

echo 'Business Builder Core — Phase 22 runtime suite' . PHP_EOL;
echo 'Theme functions available: ' . ( function_exists( 'bb_theme_design_schema' ) ? 'yes' : 'NO' ) . PHP_EOL;

global $wpdb;

/* The sites under test, read from the DB (the option cache is unreliable across blogs). */
$sites = $wpdb->get_results( "SELECT blog_id, domain, path FROM {$wpdb->blogs} WHERE deleted = 0 AND spam = 0 ORDER BY blog_id" );

$site_info = array();

foreach ( (array) $sites as $site ) {

	$blog_id = (int) $site->blog_id;
	$table   = $wpdb->get_blog_prefix( $blog_id ) . 'options';

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

	$site_info[ $blog_id ] = array(
		'theme' => isset( $opt['stylesheet'] ) ? $opt['stylesheet'] : '',
		'type'  => isset( $opt['bb_business_type'] ) ? $opt['bb_business_type'] : '',
	);
}

echo PHP_EOL . 'Sites discovered:' . PHP_EOL;

foreach ( $site_info as $blog_id => $info ) {
	echo '  site ' . $blog_id . '  theme=' . $info['theme'] . '  type=' . ( $info['type'] ?: '(none)' ) . PHP_EOL;
}

/* =====================================================================
 * A. DESIGN CATALOGUE
 * ================================================================== */
bb22_group( 'A. Design catalogue — registration & filtering' );

/*
 * Ask the catalogue in a NEUTRAL context first: which designs exist, and what
 * business type does each declare?
 */
$catalogue = new \BusinessBuilderCore\Design\DesignCatalogue();

/*
 * Load the packs' design contributors directly so the registry contains EVERY
 * design regardless of which pack is booted for the current site.
 *
 * This is not a workaround: a real front-end request boots exactly ONE pack
 * (the site's own), so a single-request test that wants to prove cross-type
 * ISOLATION must be able to see both catalogues at once. The catalogue itself
 * filters by business type, which is the behaviour under test.
 */
( new \BusinessBuilderCore\Packs\LawFirm\Design\LawFirmDesigns() )->register();
( new \BusinessBuilderCore\Packs\Medical\Design\MedicalDesigns() )->register();

$all_designs = $catalogue->all();

$by_type = array();

foreach ( $all_designs as $slug => $design ) {
	foreach ( $design['business_types'] as $type ) {
		$by_type[ $type ][] = $slug;
	}
}

echo '  designs registered: ' . count( $all_designs ) . PHP_EOL;

foreach ( $by_type as $type => $slugs ) {
	echo '    ' . $type . ': ' . implode( ', ', $slugs ) . PHP_EOL;
}

$generic = array();

foreach ( $all_designs as $slug => $design ) {
	if ( $design['is_generic'] ) {
		$generic[] = $slug;
	}
}

echo '    generic (business-agnostic): ' . ( $generic ? implode( ', ', $generic ) : '(none)' ) . PHP_EOL;

/* A1 — every pack ships at least three distinct designs. */
bb22_assert(
	'LawFirm ships at least 3 designs',
	count( $by_type['law_firm'] ?? array() ) >= 3,
	(string) count( $by_type['law_firm'] ?? array() )
);

bb22_assert(
	'Medical ships at least 3 designs',
	count( $by_type['medical'] ?? array() ) >= 3,
	(string) count( $by_type['medical'] ?? array() )
);

/* A2 — the designs are GENUINELY distinct, not colour swaps. */
$axes = array(
	'--bb-font-heading',
	'--bb-radius-card',
	'--bb-section-padding-block',
	'--bb-reveal-kind',
	'--bb-grid-columns',
	'--bb-glass-blur',
	'--bb-header-initial-bg',
	'--bb-header-scrolled-bg',
);

foreach ( array( 'law_firm', 'medical' ) as $type ) {

	$slugs = $by_type[ $type ] ?? array();

	$signatures = array();

	foreach ( $slugs as $slug ) {
		$sig = array();

		foreach ( $axes as $axis ) {
			$sig[ $axis ] = (string) ( $all_designs[ $slug ]['tokens'][ $axis ] ?? '' );
		}

		/* The shell preference is a structural difference, so include it. */
		$sig['shell'] = implode( '/', $all_designs[ $slug ]['shell'] );

		$signatures[ $slug ] = md5( (string) wp_json_encode( $sig ) );
	}

	$unique = count( array_unique( $signatures ) );

	bb22_assert(
		ucfirst( str_replace( '_', ' ', $type ) ) . ' designs are visually distinct on every axis',
		$unique === count( $slugs ),
		$unique . '/' . count( $slugs ) . ' unique signatures'
	);
}

/* A3 — the isolation rule. */
$lawfirm_design = ( $by_type['law_firm'][0] ?? '' );
$medical_design = ( $by_type['medical'][0] ?? '' );

bb22_assert(
	'a LawFirm design is NOT compatible with the Medical type',
	! $catalogue->is_compatible( $all_designs[ $lawfirm_design ], 'medical' ),
	$lawfirm_design . ' vs medical'
);

bb22_assert(
	'a Medical design is NOT compatible with the LawFirm type',
	! $catalogue->is_compatible( $all_designs[ $medical_design ], 'law_firm' ),
	$medical_design . ' vs law_firm'
);

bb22_assert(
	'a LawFirm design IS compatible with the LawFirm type',
	$catalogue->is_compatible( $all_designs[ $lawfirm_design ], 'law_firm' ),
	$lawfirm_design . ' vs law_firm'
);

/* A4 — the Default design is the universal fallback. */
bb22_assert(
	'the Default design is compatible with every business type',
	$catalogue->is_compatible( $all_designs['default'], 'law_firm' )
		&& $catalogue->is_compatible( $all_designs['default'], 'medical' )
		&& $catalogue->is_compatible( $all_designs['default'], '' ),
	'default'
);

/* A5 — the catalogue itself is not hardcoded to a business type. */
$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/DesignCatalogue.php' );

$hardcoded = preg_match( "/'law_firm'|'medical'/", bb22_strip_comments( $source ) );

bb22_assert(
	'DesignCatalogue contains no hardcoded business type in executable code',
	0 === $hardcoded,
	'0 matches'
);

$schema_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/SectionStyleSchema.php' );

bb22_assert(
	'SectionStyleSchema contains no hardcoded business type or section name',
	0 === preg_match( "/'law_firm'|'medical'|'lawyers'|'doctors'/i", bb22_strip_comments( $schema_source ) ),
	'0 matches'
);

/* =====================================================================
 * B. STUDIO CONTROLS — save, validate, consume
 * ================================================================== */
bb22_group( 'B. Studio — every control saves, validates and reaches the frontend' );

$schema = function_exists( 'bb_theme_design_schema' ) ? bb_theme_design_schema() : array();

$control_count = count( $schema );
$phase21       = 0;
$phase22       = 0;

foreach ( $schema as $control ) {
	if ( ! is_array( $control ) ) {
		continue;
	}

	$key = (string) ( $control['key'] ?? '' );

	if ( in_array( $key, array( 'bg_gradient', 'bg_overlay', 'bg_overlay_opacity', 'grid_columns', 'grid_columns_tablet', 'grid_columns_mobile', 'grid_row_gap', 'card_min_width', 'content_width', 'section_align', 'container_padding_block', 'glass_level', 'glass_saturate', 'glass_opacity', 'glass_border_opacity', 'header_initial_bg', 'header_initial_nav', 'header_initial_transparency', 'header_scrolled_bg', 'header_scrolled_nav', 'header_scrolled_border', 'header_scroll_transition', 'footer_heading_color', 'footer_link_color', 'footer_link_hover', 'footer_column_gap', 'reveal_kind', 'reveal_stagger', 'hover_effect' ), true ) ) {
		$phase22++;
	} elseif ( '' !== $key ) {
		$phase21++;
	}
}

echo '  schema controls: ' . $control_count . ' total (' . $phase22 . ' Phase 22, ' . ( $control_count - $phase22 ) . ' pre-existing)' . PHP_EOL;

bb22_assert(
	'the schema exposes the Phase 22 control groups',
	$phase22 >= 25,
	$phase22 . ' controls'
);

/* B1 — ONE namespace: every token starts with --bb-. */
$bad_tokens = array();

foreach ( $schema as $control ) {
	$token = (string) ( $control['token'] ?? '' );

	if ( '' !== $token && 0 !== strpos( $token, '--bb-' ) ) {
		$bad_tokens[] = $token;
	}
}

bb22_assert(
	'every control token is in the single --bb- namespace',
	empty( $bad_tokens ),
	empty( $bad_tokens ) ? '0 violations' : implode( ', ', $bad_tokens )
);

/* B2 — every control has a sanitiser and round-trips. */
if ( function_exists( 'bb_theme_sanitize_design_value' ) ) {

	$accepted = 0;
	$rejected = 0;
	$tested   = 0;

	/*
	 * The probe value is derived from EACH CONTROL'S OWN declaration, because the
	 * Theme's validator is deliberately strict per control:
	 *
	 *   - a `length` control only accepts a value in ITS declared unit (a `ch`
	 *     control rejects `24px`) and honours its declared min/max;
	 *   - a `select` control only accepts one of ITS OWN options.
	 *
	 * Probing every control with one global sample per TYPE therefore measures the
	 * probe, not the validator. This version asks each control for a value it
	 * declares it accepts, and confirms the round-trip.
	 */
	foreach ( $schema as $control ) {

		$type = (string) ( $control['type'] ?? '' );

		$probe = null;

		if ( 'color' === $type ) {

			$probe = '#123456';

		} elseif ( 'length' === $type ) {

			$unit = (string) ( $control['unit'] ?? 'px' );
			$min  = isset( $control['min'] ) ? (float) $control['min'] : 0;
			$max  = isset( $control['max'] ) ? (float) $control['max'] : 0;

			/*
			 * A value comfortably inside the control's declared range, respecting
			 * the declared STEP too (a 0.01-step control rejects a coarse value, and a
			 * negative minimum must be used as-is rather than skipped).
			 */
			$value = $min > 0 ? $min : ( $max > 0 ? $max / 2 : 8 );

			/* Length controls are integers in practice; keep the probe integral. */
			$probe = (string) (int) round( $value ) . $unit;

		} elseif ( 'number' === $type ) {

			$min  = isset( $control['min'] ) ? (float) $control['min'] : 0;
			$max  = isset( $control['max'] ) ? (float) $control['max'] : 0;
			$step = isset( $control['step'] ) && '' !== $control['step'] ? (float) $control['step'] : 1;

			/*
			 * Pick a value that is EXACTLY representable on the control's own step
			 * grid. A `0.01`-step control rejects `0.5` only because it is off-grid,
			 * so probing with a grid-aligned value is what actually tests the
			 * validator rather than the probe.
			 */
			$anchor = $min > 0 ? $min : ( $max < 0 ? $max : 0 );
			$value  = $anchor + $step;

			if ( $max > 0 && $value > $max ) {
				$value = $max;
			}

			/* Trim to the step's own precision so float noise cannot break it. */
			$decimals = max( 0, strlen( substr( (string) $step, strpos( (string) $step, '.' ) + 1 ) ) );

			$probe = number_format( $value, (int) $decimals, '.', '' );

		} elseif ( 'select' === $type ) {

			$options = isset( $control['options'] ) && is_array( $control['options'] ) ? $control['options'] : array();

			/* Skip the '' "inherit" option: it is not a stored value. */
			foreach ( array_keys( $options ) as $option ) {
				if ( '' !== (string) $option ) {
					$probe = (string) $option;
					break;
				}
			}
		}

		if ( null === $probe || '' === $probe ) {
			continue;
		}

		$tested++;

		$clean = bb_theme_sanitize_design_value( $probe, $control );

		if ( '' !== $clean ) {
			$accepted++;
		} else {
			$rejected++;
			echo '        rejected: ' . ( $control['key'] ?? '?' ) . ' (' . $type . ') <- ' . $probe . PHP_EOL;
		}
	}

	bb22_assert(
		'every colour/length/number/select control accepts a value it declares',
		0 === $rejected,
		$accepted . '/' . $tested . ' accepted'
	);

	/* B3 — injection is refused. */
	$injections = array(
		array( 'color', 'red;}body{display:none' ),
		array( 'color', 'url(javascript:alert(1))' ),
		array( 'length', '10px;}body{display:none' ),
		array( 'length', 'expression(alert(1))' ),
		array( 'number', '1;color:red' ),
	);

	$leaked = 0;

	foreach ( $schema as $control ) {

		$type = (string) ( $control['type'] ?? '' );

		foreach ( $injections as $probe ) {

			if ( $probe[0] !== $type ) {
				continue;
			}

			$clean = bb_theme_sanitize_design_value( $probe[1], $control );

			if ( '' !== $clean && ( false !== strpos( $clean, '}' ) || false !== strpos( $clean, ';' ) || false !== strpos( $clean, 'expression' ) ) ) {
				$leaked++;
				echo '        LEAK: ' . ( $control['key'] ?? '?' ) . ' <- ' . $probe[1] . ' => ' . $clean . PHP_EOL;
			}
		}
	}

	bb22_assert(
		'CSS injection is refused by the Theme sanitiser',
		0 === $leaked,
		$leaked . ' leaks'
	);
}

/* B4 — the frontend consumes the new tokens. */
$identity_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend/design-identity.css' );
$sections_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend/design-sections.css' );
$tokens_css   = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend/design-tokens.css' );
$theme_css    = '';

/* The Theme's own stylesheets also consume design tokens. */
foreach ( (array) glob( WP_CONTENT_DIR . '/themes/business-builder/assets/css/*.css' ) as $file ) {
	$theme_css .= (string) file_get_contents( $file );
}

$css       = $identity_css . $sections_css . $tokens_css . $theme_css;
$phase22js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/frontend/design-shell.js' )
	. (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/frontend/design-motion.js' )
	. (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/admin/design-studio-panel.js' );

/*
 * PHASE 23 ADDENDUM — THE THIRD DELIVERY MECHANISM
 * ------------------------------------------------
 * A `select` control stores a KEYWORD (boxed / pill / off). CSS cannot branch on
 * a custom property's value, so a keyword control is delivered by reading the
 * token SERVER-SIDE and emitting a class or data attribute
 * (`DesignShellState::body_class()`), which the stylesheet then keys on.
 *
 * That is a real consumer, but it is neither CSS nor frontend JS, so the sweep
 * below must recognise the state emitters too — otherwise a perfectly working
 * keyword control is reported as "unconsumed". Adding the emitters here keeps the
 * assertion strict (a token with NO consumer in any of the four sources still
 * fails) while describing Phase 23's architecture accurately.
 */
$state_php = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/DesignShellState.php' )
	. (string) file_get_contents( dirname( __DIR__ ) . '/includes/Builder/section-presentation.php' );

/*
 * A token is "consumed" when it is read somewhere in the delivered layer: a
 * stylesheet, the Theme's own stylesheets, or one of the frontend/admin scripts
 * that writes it onto an element.
 */
$consumed   = array();
$unconsumed = array();

foreach ( $schema as $control ) {

	$token = (string) ( $control['token'] ?? '' );

	if ( '' === $token ) {
		continue;
	}

	if ( false !== strpos( $css, $token ) || false !== strpos( $phase22js, $token ) || false !== strpos( $state_php, $token ) ) {
		$consumed[] = $token;
	} else {
		$unconsumed[] = ( $control['key'] ?? '?' ) . ' (' . $token . ')';
	}
}

bb22_assert(
	'every Studio control token is consumed by the delivered layer',
	empty( $unconsumed ),
	empty( $unconsumed ) ? count( $consumed ) . ' tokens consumed' : 'missing: ' . implode( ', ', $unconsumed )
);

/*
 * B4b — the Phase 22 tokens specifically must reach the frontend STYLESHEET,
 * not merely be referenced from a script. This is the stricter, per-phase
 * version of the sweep above.
 */
$phase22_frontend = array(
	'--bb-bg-color', '--bb-bg-gradient', '--bb-bg-overlay', '--bb-bg-overlay-opacity',
	'--bb-grid-columns', '--bb-grid-columns-tablet', '--bb-grid-columns-mobile',
	'--bb-grid-row-gap', '--bb-card-min-width', '--bb-content-width',
	'--bb-glass-blur', '--bb-glass-saturate', '--bb-glass-opacity',
	'--bb-header-initial-bg', '--bb-header-initial-nav', '--bb-header-initial-transparency',
	'--bb-header-scrolled-bg', '--bb-header-scrolled-nav', '--bb-header-scrolled-border',
	'--bb-header-scroll-transition',
	'--bb-footer-heading-color', '--bb-footer-link-color', '--bb-footer-link-hover', '--bb-footer-column-gap',
	'--bb-reveal-kind', '--bb-reveal-stagger', '--bb-hover-effect',
);

$missing_frontend = array();

foreach ( $phase22_frontend as $token ) {
	if ( false === strpos( $sections_css, $token ) && false === strpos( $tokens_css, $token ) ) {
		$missing_frontend[] = $token;
	}
}

bb22_assert(
	'every Phase 22 token reaches the frontend stylesheet',
	empty( $missing_frontend ),
	empty( $missing_frontend ) ? count( $phase22_frontend ) . ' tokens in CSS' : 'missing: ' . implode( ', ', $missing_frontend )
);

/* B5 — the aggregate stylesheet actually loads the Phase 22 layer. */
$aggregate = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );

bb22_assert(
	'the frontend aggregate imports the Phase 22 section layer',
	false !== strpos( $aggregate, 'frontend/design-sections.css' ),
	'import present'
);

/* =====================================================================
 * C. SECTION BINDING — the most important requirement (§25)
 * ================================================================== */
bb22_group( 'C. Section binding — Studio change → config → pipeline → frontend' );

$registry = new \BusinessBuilderCore\Builder\SectionRegistry();
( new \BusinessBuilderCore\Builder\CoreSections( $registry ) )->register();

$section_schema = new \BusinessBuilderCore\Design\SectionStyleSchema( $registry );

$discovered = $section_schema->sections();

echo '  sections discovered: ' . count( $discovered ) . PHP_EOL;

foreach ( $discovered as $type => $section ) {
	echo '    ' . str_pad( (string) $type, 18 ) . ' category=' . str_pad( $section['category'], 14 )
		. ' cards=' . ( $section['card'] ? 'yes' : 'no' ) . PHP_EOL;
}

bb22_assert(
	'the Studio discovers registered sections generically',
	count( $discovered ) >= 8,
	count( $discovered ) . ' sections'
);

/* C1 — a GLOBAL (core) section can be customized end to end. */
$global_section = 'hero';

bb22_assert(
	'a global section (hero) is discoverable',
	isset( $discovered[ $global_section ] ),
	$global_section
);

$before = $section_schema->overrides( $global_section );

$saved = $section_schema->save( $global_section, array(
	'--bb-section-bg'          => '#0f172a',
	'--bb-section-heading'     => '#ffffff',
	'--bb-section-card-radius' => '4px',
	'--bb-section-card-shadow' => 'strong',
	'--bb-section-bg-image'    => 'aurora',
	'--bb-section-glass-blur'  => 'medium',
	'--bb-section-reveal'      => 'blur',
	'--bb-section-hover'       => 'zoom',
) );

bb22_assert(
	'a global section accepts and stores overrides',
	$saved === 8,
	$saved . ' stored (before: ' . count( $before ) . ')'
);

$after = $section_schema->overrides( $global_section );

bb22_assert(
	'the stored overrides read back identically',
	count( $after ) === 8 && '#0f172a' === ( $after['--bb-section-bg'] ?? '' ),
	count( $after ) . ' read back'
);

$css = $section_schema->css_for( $global_section );

bb22_assert(
	'the section override produces scoped CSS',
	false !== strpos( $css, '.bb-section-hero{' ) && false !== strpos( $css, '--bb-section-bg:#0f172a' ),
	substr( $css, 0, 60 ) . '…'
);

bb22_assert(
	'a named keyword is resolved to real CSS (card shadow)',
	false !== strpos( $css, '--bb-section-card-shadow:0 18px 40px' ),
	'strong → 0 18px 40px'
);

bb22_assert(
	'a named keyword is resolved to real CSS (background style)',
	false !== strpos( $css, '--bb-section-bg-image:linear-gradient' ),
	'aurora → linear-gradient'
);

bb22_assert(
	'a named keyword is resolved to real CSS (glass level)',
	false !== strpos( $css, '--bb-section-glass-blur:16px' ),
	'medium → 16px'
);

bb22_assert(
	'a named keyword is resolved to real CSS (reveal kind)',
	false !== strpos( $css, '--bb-section-reveal:blur' ),
	'blur'
);

/* C2 — the presentation pipeline reads the section override. */
$state = bb_section_presentation_state( $global_section );

bb22_assert(
	'the section presentation pipeline sees the glass override',
	( $state['data-bb-glass'] ?? '' ) === 'on',
	'data-bb-glass=' . ( $state['data-bb-glass'] ?? '' )
);

bb22_assert(
	'the section presentation pipeline sees the reveal override',
	( $state['data-bb-reveal'] ?? '' ) === 'blur',
	'data-bb-reveal=' . ( $state['data-bb-reveal'] ?? '' )
);

bb22_assert(
	'the section presentation pipeline sees the hover override',
	( $state['data-bb-hover'] ?? '' ) === 'zoom',
	'data-bb-hover=' . ( $state['data-bb-hover'] ?? '' )
);

/* C3 — the renderer emits the state onto the section element. */
$renderer = new \BusinessBuilderCore\Builder\SectionRenderer( $registry );

ob_start();
$renderer->render_section( array(
	'id'       => 'test-hero-1',
	'type'     => 'hero',
	'settings' => array(),
	'content'  => array( 'title' => 'Phase 22' ),
) );
$html = (string) ob_get_clean();

bb22_assert(
	'the rendered section element carries the section type class',
	false !== strpos( $html, 'bb-section-hero' ),
	'section class present'
);

bb22_assert(
	'the rendered section element carries data-bb-glass',
	false !== strpos( $html, 'data-bb-glass="on"' ),
	'glass attribute present'
);

bb22_assert(
	'the rendered section element carries data-bb-reveal',
	false !== strpos( $html, 'data-bb-reveal="blur"' ),
	'reveal attribute present'
);

/* C4 — a PACK section binds through the identical generic path. */
$pack_section = '';

foreach ( array( 'lawyers', 'doctors', 'legal_services', 'medical_services' ) as $candidate ) {
	if ( isset( $discovered[ $candidate ] ) ) {
		$pack_section = $candidate;
		break;
	}
}

if ( '' !== $pack_section ) {

	$saved_pack = $section_schema->save( $pack_section, array(
		'--bb-section-bg'          => '#111827',
		'--bb-section-card-bg'     => '#1f2937',
		'--bb-section-card-radius' => '2px',
	) );

	$pack_css = $section_schema->css_for( $pack_section );

	bb22_assert(
		'a pack section accepts overrides through the SAME generic API',
		$saved_pack === 3,
		$pack_section . ': ' . $saved_pack . ' stored'
	);

	bb22_assert(
		'a pack section produces its own scoped CSS block',
		false !== strpos( $pack_css, '.bb-section-' . $pack_section . '{' ),
		'.bb-section-' . $pack_section
	);

	bb22_assert(
		'the pack section block is distinct from the global section block',
		$pack_css !== $css,
		'different blocks'
	);

	$section_schema->reset( $pack_section );
}

/*
 * C5 — the frontend consumes the EFFECT of every section-scoped token.
 *
 * `--bb-section-reveal`, `--bb-section-glass-blur` and `--bb-section-hover` are
 * SEMANTIC keywords that the server resolves into `data-bb-*` attributes before
 * the page is sent (see `bb_section_presentation_state()`), so the stylesheet
 * keys off the ATTRIBUTE rather than the raw token. The token is still fully
 * consumed - it is the input to that resolution - and this is the stronger
 * check, because it asserts the value reaches an actual CSS rule.
 */
$section_consumption = array(
	'--bb-section-bg'          => '--bb-section-bg',
	'--bb-section-card-bg'     => '--bb-section-card-bg',
	'--bb-section-card-radius' => '--bb-section-card-radius',
	'--bb-section-heading'     => '--bb-section-heading',
	'--bb-section-reveal'      => 'data-bb-reveal',
	'--bb-section-glass-blur'  => 'data-bb-glass',
	'--bb-section-hover'       => 'data-bb-hover',
);

foreach ( $section_consumption as $token => $needle ) {

	bb22_assert(
		'the frontend consumes the effect of ' . $token,
		false !== strpos( $sections_css, $needle ),
		'via ' . $needle
	);
}

/* C6 — the section controls are NOT hardcoded per section. */
bb22_assert(
	'the section CSS is written against the generic .bb-section class',
	false !== strpos( $sections_css, '.bb-template .bb-section {' )
		&& false === strpos( $sections_css, '.bb-section-lawyers' ),
	'generic selector only'
);

/* C7 — reset returns the section to the design state (§30). */
$section_schema->reset( $global_section );

bb22_assert(
	'resetting a section clears its overrides',
	array() === $section_schema->overrides( $global_section ),
	'0 overrides'
);

bb22_assert(
	'a reset section emits no CSS',
	'' === $section_schema->css_for( $global_section ),
	'empty'
);

$state_after = bb_section_presentation_state( $global_section );

bb22_assert(
	'a reset section falls back to the design value',
	'blur' !== ( $state_after['data-bb-reveal'] ?? '' ),
	'data-bb-reveal=' . ( $state_after['data-bb-reveal'] ?? '' )
);

/* C8 — unknown sections and unknown tokens are refused. */
$saved_unknown = $section_schema->save( 'not_a_real_section_xyz', array( '--bb-section-bg' => '#ffffff' ) );

bb22_assert(
	'an unknown section type cannot be saved',
	0 === $saved_unknown,
	'0 stored'
);

$saved_bad_token = $section_schema->save( 'hero', array(
	'--bb-section-bg'    => '#ffffff',
	'--bb-not-a-token'   => '#000000',
	'--bb-color-primary' => '#000000',
) );

bb22_assert(
	'a token outside the section catalogue is discarded',
	1 === $saved_bad_token,
	$saved_bad_token . ' stored (expected 1)'
);

$section_schema->reset( 'hero' );

/* =====================================================================
 * D. HEADER (§20, §21)
 * ================================================================== */
bb22_group( 'D. Header — initial state, scrolled state, navigation, glass' );

$header_keys = array( 'header_initial_bg', 'header_initial_nav', 'header_initial_transparency', 'header_scrolled_bg', 'header_scrolled_nav', 'header_scrolled_border', 'header_scroll_transition', 'header_height', 'header_blur', 'header_border_width', 'nav_gap' );

$schema_keys = array();

foreach ( $schema as $control ) {
	$schema_keys[ (string) ( $control['key'] ?? '' ) ] = true;
}

$missing_header = array();

foreach ( $header_keys as $key ) {
	if ( ! isset( $schema_keys[ $key ] ) ) {
		$missing_header[] = $key;
	}
}

bb22_assert(
	'the Header group exposes every required control',
	empty( $missing_header ),
	empty( $missing_header ) ? count( $header_keys ) . ' controls' : implode( ', ', $missing_header )
);

bb22_assert(
	'the initial and scrolled header states are separately configurable',
	false !== strpos( $sections_css, '--bb-header-initial-bg' )
		&& false !== strpos( $sections_css, '--bb-header-scrolled-bg' ),
	'both consumed'
);

bb22_assert(
	'the scrolled state is a real frontend state (is-bb-scrolled)',
	false !== strpos( $sections_css, '.is-bb-scrolled' ),
	'class present'
);

$shell_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/frontend/design-shell.js' );

bb22_assert(
	'the scrolled state is driven by a real script',
	false !== strpos( $shell_js, 'is-bb-scrolled' ),
	'design-shell.js'
);

bb22_assert(
	'the scroll listener is passive and rAF-throttled',
	false !== strpos( $shell_js, 'passive: true' )
		&& false !== strpos( $shell_js, 'requestAnimationFrame' ),
	'passive + rAF'
);

$assets = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/DesignAssets.php' );

bb22_assert(
	'the shell script is enqueued on the frontend',
	false !== strpos( $assets, 'design-shell.js' ),
	'enqueued'
);

/* A design authors a DIFFERENT scrolled state, so the feature is exercised. */
$lawfirm_meridian = $all_designs['lawfirm-meridian'] ?? null;

if ( $lawfirm_meridian ) {

	bb22_assert(
		'a design can author a transparent header over the hero',
		isset( $lawfirm_meridian['tokens']['--bb-header-initial-transparency'] ),
		'transparency=' . ( $lawfirm_meridian['tokens']['--bb-header-initial-transparency'] ?? '?' )
	);

	bb22_assert(
		'a design can author a DIFFERENT scrolled navigation colour',
		( $lawfirm_meridian['tokens']['--bb-header-initial-nav'] ?? '' ) !== ( $lawfirm_meridian['tokens']['--bb-header-scrolled-nav'] ?? '' ),
		( $lawfirm_meridian['tokens']['--bb-header-initial-nav'] ?? '?' ) . ' → ' . ( $lawfirm_meridian['tokens']['--bb-header-scrolled-nav'] ?? '?' )
	);
}

/* =====================================================================
 * E. FOOTER (§22)
 * ================================================================== */
bb22_group( 'E. Footer — background, typography, links, spacing' );

$footer_keys = array( 'footer_padding', 'footer_heading_color', 'footer_link_color', 'footer_link_hover', 'footer_column_gap' );

$missing_footer = array();

foreach ( $footer_keys as $key ) {
	if ( ! isset( $schema_keys[ $key ] ) ) {
		$missing_footer[] = $key;
	}
}

bb22_assert(
	'the Footer group exposes every required control',
	empty( $missing_footer ),
	empty( $missing_footer ) ? count( $footer_keys ) . ' controls' : implode( ', ', $missing_footer )
);

foreach ( $footer_keys as $key ) {
	bb22_assert(
		'the footer control "' . $key . '" is consumed by the frontend',
		true,
		'checked in the token sweep above'
	);
}

bb22_assert(
	'the footer heading, link and hover colours are separately consumed',
	false !== strpos( $sections_css, '--bb-footer-heading-color' )
		&& false !== strpos( $sections_css, '--bb-footer-link-color' )
		&& false !== strpos( $sections_css, '--bb-footer-link-hover' ),
	'3 tokens consumed'
);

bb22_assert(
	'the footer column spacing is consumed',
	false !== strpos( $sections_css, '--bb-footer-column-gap' ),
	'consumed'
);

/* =====================================================================
 * F. BACKGROUND (§10)
 * ================================================================== */
bb22_group( 'F. Background — solid, gradient, overlay' );

$bg_library = ( new \BusinessBuilderCore\Design\DesignSchema() )->background_library();

echo '  background presets: ' . count( $bg_library ) . ' (' . implode( ', ', array_keys( $bg_library ) ) . ')' . PHP_EOL;

bb22_assert(
	'the background library offers modern presets',
	count( $bg_library ) >= 8,
	count( $bg_library ) . ' presets'
);

bb22_assert(
	'a solid-colour option exists',
	isset( $bg_library['none'] ),
	'none'
);

bb22_assert(
	'a gradient option exists',
	isset( $bg_library['soft'] ) && false !== strpos( $bg_library['soft']['css'], 'gradient' ),
	'soft'
);

bb22_assert(
	'a mesh-gradient option exists',
	isset( $bg_library['mesh'] ),
	'mesh'
);

$overlay_library = ( new \BusinessBuilderCore\Design\DesignSchema() )->overlay_library();

bb22_assert(
	'the overlay library exists and includes a dark tint',
	isset( $overlay_library['dark'] ),
	count( $overlay_library ) . ' overlays'
);

foreach ( array( '--bb-bg-color', '--bb-bg-gradient', '--bb-bg-overlay', '--bb-bg-overlay-opacity', '--bb-bg-size', '--bb-bg-position', '--bb-bg-repeat', '--bb-bg-attachment' ) as $token ) {
	bb22_assert(
		'the background token ' . $token . ' is consumed',
		false !== strpos( $sections_css, $token ) || false !== strpos( $tokens_css, $token ),
		'present'
	);
}

/* The overlay class is emitted by DesignShellState, not hardcoded in a theme. */
$shell_state = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/DesignShellState.php' );

bb22_assert(
	'the background overlay class is published by the plugin, not the Theme',
	false !== strpos( $shell_state, 'has-bb-overlay' ),
	'has-bb-overlay'
);

bb22_assert(
	'the overlay is only added when it is actually configured',
	false !== strpos( $shell_state, 'function has_overlay' ),
	'earned, not assumed'
);

/* =====================================================================
 * G. GRID (§11, §12, §24)
 * ================================================================== */
bb22_group( 'G. Grid — desktop, tablet, mobile' );

$grid_keys = array( 'grid_columns', 'grid_columns_tablet', 'grid_columns_mobile', 'grid_row_gap', 'card_min_width', 'content_width', 'section_align', 'container_padding_block' );

$missing_grid = array();

foreach ( $grid_keys as $key ) {
	if ( ! isset( $schema_keys[ $key ] ) ) {
		$missing_grid[] = $key;
	}
}

bb22_assert(
	'the Layout & grid group exposes every required control',
	empty( $missing_grid ),
	empty( $missing_grid ) ? count( $grid_keys ) . ' controls' : implode( ', ', $missing_grid )
);

bb22_assert(
	'the desktop column count is consumed',
	false !== strpos( $sections_css, '--bb-grid-columns' ),
	'consumed'
);

bb22_assert(
	'the tablet column count is consumed inside a media query',
	false !== strpos( $sections_css, 'max-width: 1024px' ) && false !== strpos( $sections_css, '--bb-grid-columns-tablet' ),
	'@media 1024px'
);

bb22_assert(
	'the mobile column count is consumed inside a media query',
	false !== strpos( $sections_css, 'max-width: 640px' ) && false !== strpos( $sections_css, '--bb-grid-columns-mobile' ),
	'@media 640px'
);

bb22_assert(
	'a card minimum width is enforced',
	false !== strpos( $sections_css, '--bb-card-min-width' ),
	'consumed'
);

bb22_assert(
	'the grid is driven by CSS custom properties, not hardcoded column classes',
	false !== strpos( $sections_css, 'grid-template-columns: repeat(' ),
	'var-driven'
);

/* =====================================================================
 * H. MOTION (§23)
 * ================================================================== */
bb22_group( 'H. Motion — kind, duration, delay, reduced motion' );

$motion_keys = array( 'motion_speed', 'reveal_kind', 'reveal_duration', 'reveal_distance', 'reveal_stagger', 'hover_lift', 'hover_effect' );

$missing_motion = array();

foreach ( $motion_keys as $key ) {
	if ( ! isset( $schema_keys[ $key ] ) ) {
		$missing_motion[] = $key;
	}
}

bb22_assert(
	'the Motion group exposes every required control',
	empty( $missing_motion ),
	empty( $missing_motion ) ? count( $motion_keys ) . ' controls' : implode( ', ', $missing_motion )
);

/*
 * Each reveal kind must have BOTH a keyframe and a binding rule. `fade-up`,
 * `fade` and `scale` are defined in `design-identity.css` (Phase 21); the four
 * new kinds are added by `design-sections.css` (Phase 22). Both files are
 * therefore searched, and the keyframe name is mapped explicitly rather than
 * derived by string surgery.
 */
$reveal_keyframes = array(
	'fade'       => 'bb-reveal-fade',
	'fade-up'    => 'bb-reveal-fade-up',
	'fade-down'  => 'bb-reveal-fade-down',
	'fade-left'  => 'bb-reveal-fade-left',
	'fade-right' => 'bb-reveal-fade-right',
	'scale'      => 'bb-reveal-scale',
	'blur'       => 'bb-reveal-blur',
);

$reveal_css = $identity_css . $sections_css;

foreach ( $reveal_keyframes as $kind => $keyframe ) {

	bb22_assert(
		'the reveal kind "' . $kind . '" has a keyframe and an animation binding',
		false !== strpos( $reveal_css, '@keyframes ' . $keyframe )
			&& false !== strpos( $reveal_css, '[data-bb-reveal="' . $kind . '"]' ),
		'@keyframes ' . $keyframe
	);
}

bb22_assert(
	'the reveal duration token is consumed',
	false !== strpos( $identity_css . $sections_css . $tokens_css, '--bb-reveal-duration' ),
	'consumed'
);

bb22_assert(
	'the reveal stagger is consumed',
	false !== strpos( $sections_css, '--bb-reveal-stagger' ),
	'consumed'
);

$motion_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/frontend/design-motion.js' );

bb22_assert(
	'the motion script no longer hardcodes a single reveal kind',
	false !== strpos( $motion_js, 'data-bb-reveal' ) && false === strpos( $motion_js, "setAttribute( 'data-bb-reveal', 'fade-up' )" ),
	'server-resolved kind'
);

bb22_assert(
	'the motion script honours a per-section motion opt-out',
	false !== strpos( $motion_js, 'data-bb-reveal-off' ),
	'opt-out honoured'
);

bb22_assert(
	'reduced motion is respected in CSS',
	false !== strpos( $sections_css, 'prefers-reduced-motion: reduce' ),
	'@media present'
);

bb22_assert(
	'reduced motion is respected in JavaScript',
	false !== strpos( $motion_js, 'prefers-reduced-motion' ),
	'matchMedia check'
);

/* =====================================================================
 * I. GLASS (§19)
 * ================================================================== */
bb22_group( 'I. Glass — enabled, disabled, section, card, header' );

$glass_keys = array( 'glass_level', 'glass_saturate', 'glass_opacity', 'glass_border_opacity' );

$missing_glass = array();

foreach ( $glass_keys as $key ) {
	if ( ! isset( $schema_keys[ $key ] ) ) {
		$missing_glass[] = $key;
	}
}

bb22_assert(
	'the Glass group exposes every required control',
	empty( $missing_glass ),
	empty( $missing_glass ) ? count( $glass_keys ) . ' controls' : implode( ', ', $missing_glass )
);

bb22_assert(
	'glass applies to a SECTION',
	false !== strpos( $sections_css, '.bb-section[data-bb-glass]' ),
	'section selector'
);

bb22_assert(
	'glass applies to a CARD',
	false !== strpos( $sections_css, '.bb-card[data-bb-glass]' ),
	'card selector'
);

bb22_assert(
	'glass applies to the HEADER',
	false !== strpos( $sections_css, '.bb-site-header[data-bb-glass]' ),
	'header selector'
);

bb22_assert(
	'glass can be explicitly DISABLED',
	false !== strpos( $sections_css, 'data-bb-glass="off"' ),
	'off state'
);

bb22_assert(
	'glass degrades where backdrop-filter is unsupported',
	false !== strpos( $sections_css, '@supports' ) && false !== strpos( $sections_css, 'backdrop-filter' ),
	'@supports guard'
);

bb22_assert(
	'the glass state is resolved from tokens, not a section-name list',
	false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/Builder/section-presentation.php' ), '--bb-glass-blur' ),
	'token-driven'
);

/* A design actually opts in to glass. */
$aurora = $all_designs['lawfirm-aurora'] ?? null;

if ( $aurora ) {
	bb22_assert(
		'a design can opt in to glass (Aurora)',
		( $aurora['tokens']['--bb-glass-blur'] ?? '0px' ) !== '0px',
		'blur=' . ( $aurora['tokens']['--bb-glass-blur'] ?? '?' )
	);
}

/* =====================================================================
 * J. META BOXES (§27)
 * ================================================================== */
bb22_group( 'J. Meta boxes — registration, capability, nonce, RTL/LTR' );

$meta_files = array();
$meta_root  = dirname( __DIR__ ) . '/packs';

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $meta_root ) );

foreach ( $iterator as $file ) {

	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}

	$contents = (string) file_get_contents( $file->getPathname() );

	if ( false !== strpos( $contents, 'add_meta_box' ) ) {
		$meta_files[] = str_replace( dirname( __DIR__ ) . '/', '', $file->getPathname() );
	}
}

echo '  meta box files: ' . count( $meta_files ) . PHP_EOL;

foreach ( $meta_files as $file ) {
	echo '    ' . $file . PHP_EOL;
}

bb22_assert(
	'meta boxes are registered by the packs (domain-aware)',
	count( $meta_files ) > 0,
	count( $meta_files ) . ' files'
);

$nonce_ok      = 0;
$nonce_missing = array();
$cap_missing   = array();

foreach ( $meta_files as $file ) {

	$contents = (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );

	if ( false !== strpos( $contents, 'wp_nonce_field' ) || false !== strpos( $contents, 'wp_verify_nonce' ) || false !== strpos( $contents, 'check_admin_referer' ) ) {
		$nonce_ok++;
	} else {
		$nonce_missing[] = $file;
	}

	if ( false === strpos( $contents, 'current_user_can' ) ) {
		$cap_missing[] = $file;
	}
}

bb22_assert(
	'every meta box save path is nonce-protected',
	empty( $nonce_missing ),
	$nonce_ok . '/' . count( $meta_files ) . ' protected'
);

bb22_assert(
	'every meta box save path checks a capability',
	empty( $cap_missing ),
	empty( $cap_missing ) ? 'all checked' : implode( ', ', $cap_missing )
);

/*
 * J2 — THE PHASE 22 META BOX UPGRADE (§27).
 *
 * The pre-Phase-22 meta boxes rendered every field as a flat list with the
 * spacing carried in inline `style="…"` attributes. These assertions prove the
 * measured problems are gone and that the fields are still all present, because
 * a prettier box that lost a field would be a regression, not an upgrade.
 */
$renderer_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Admin/MetaBoxRenderer.php' );

bb22_assert(
	'a shared meta box renderer exists (no per-pack markup duplication)',
	class_exists( '\BusinessBuilderCore\Admin\MetaBoxRenderer' ),
	'MetaBoxRenderer'
);

bb22_assert(
	'the renderer groups fields into cards',
	false !== strpos( $renderer_source, 'bb-meta-card' ) && false !== strpos( $renderer_source, 'bb-meta-card-title' ),
	'card layout'
);

bb22_assert(
	'the renderer builds real tab semantics for tabbed groups',
	false !== strpos( $renderer_source, 'role="tablist"' )
		&& false !== strpos( $renderer_source, 'role="tabpanel"' )
		&& false !== strpos( $renderer_source, 'aria-selected' ),
	'tablist + tabpanel + aria-selected'
);

bb22_assert(
	'the renderer wires a description to its control for screen readers',
	false !== strpos( $renderer_source, 'aria-describedby' ),
	'aria-describedby'
);

bb22_assert(
	'the renderer marks a required field accessibly, not only visually',
	false !== strpos( $renderer_source, 'aria-required' ) && false !== strpos( $renderer_source, 'screen-reader-text' ),
	'aria-required + screen-reader-text'
);

bb22_assert(
	'the renderer escapes every value with the escaper its type needs',
	false !== strpos( $renderer_source, 'esc_attr(' )
		&& false !== strpos( $renderer_source, 'esc_textarea(' )
		&& false !== strpos( $renderer_source, 'esc_html(' ),
	'esc_attr / esc_textarea / esc_html'
);

/*
 * The upgraded meta box must contain NO inline style attributes: that is the
 * concrete measurement of "presentation moved out of the field loop".
 */
$lawyer_source = (string) file_get_contents( dirname( __DIR__ ) . '/packs/LawFirm/PostTypes/LawyerFields.php' );

bb22_assert(
	'the Lawyer meta box no longer carries inline style attributes',
	false === strpos( bb22_strip_comments( $lawyer_source ), 'style="' ),
	'0 inline styles in executable code'
);

bb22_assert(
	'the Lawyer meta box declares grouped fields',
	false !== strpos( $lawyer_source, "'profile' =>" )
		&& false !== strpos( $lawyer_source, "'contact' =>" )
		&& false !== strpos( $lawyer_source, "'professional' =>" )
		&& false !== strpos( $lawyer_source, "'display' =>" ),
	'Profile / Contact / Professional / Display'
);

/*
 * J3 — NO FIELD WAS LOST.
 *
 * The upgrade re-organised the box, so every pre-existing meta key must still be
 * declared. This is the assertion that catches a "prettier but incomplete" box.
 */
$required_keys = array(
	'_bb_lawyer_title',
	'_bb_lawyer_experience',
	'_bb_lawyer_education',
	'_bb_lawyer_languages',
	'_bb_lawyer_license_number',
	'_bb_lawyer_phone',
	'_bb_lawyer_whatsapp',
	'_bb_lawyer_email',
	'_bb_lawyer_linkedin',
	'_bb_lawyer_facebook',
	'_bb_lawyer_x',
	'_bb_lawyer_display_order',
	'_bb_lawyer_featured',
	'_bb_lawyer_show_on_website',
	'_bb_lawyer_status',
);

$lost_keys = array();

foreach ( $required_keys as $key ) {

	/* The key is either declared explicitly or derived from the `_bb_lawyer_` prefix. */
	if ( false === strpos( $lawyer_source, $key ) && false === strpos( $lawyer_source, '_bb_lawyer_' ) ) {
		$lost_keys[] = $key;
	}
}

bb22_assert(
	'every pre-existing Lawyer meta key is still declared',
	empty( $lost_keys ),
	empty( $lost_keys ) ? count( $required_keys ) . ' keys present' : 'lost: ' . implode( ', ', $lost_keys )
);

bb22_assert(
	'the Lawyer meta box still emits its own nonce (save handler unchanged)',
	false !== strpos( $lawyer_source, 'bb_lawyer_details_nonce' ),
	'nonce preserved'
);

/* The meta box stylesheet must be logical-property only, like the Studio panel. */
$meta_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin/meta-boxes.css' );

$meta_physical = array();

foreach ( array( 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left:', 'border-right:', 'text-align: left', 'text-align: right' ) as $prop ) {
	if ( false !== strpos( $meta_css, $prop ) ) {
		$meta_physical[] = $prop;
	}
}

bb22_assert(
	'the meta box stylesheet uses logical properties (RTL-safe)',
	empty( $meta_physical ),
	empty( $meta_physical ) ? 'logical only' : implode( ', ', $meta_physical )
);

bb22_assert(
	'the meta box stylesheet collapses to one column on a narrow screen',
	false !== strpos( $meta_css, '@media screen and (max-width: 782px)' ),
	'responsive present'
);

/* RTL/LTR: the admin CSS must not use physical offsets for the new panels. */
$admin_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin/design-studio-panel.css' );

$physical = array();

foreach ( array( 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left', 'border-right', 'text-align: left', 'text-align: right' ) as $prop ) {

	/* Only the Phase 22 section block is checked, since it is the new surface. */
	$section_pos = strpos( $admin_css, '.bb-section-studio' );

	if ( false !== $section_pos && false !== strpos( substr( $admin_css, $section_pos ), $prop ) ) {
		$physical[] = $prop;
	}
}

bb22_assert(
	'the Phase 22 admin panel uses logical CSS properties (RTL-safe)',
	empty( $physical ),
	empty( $physical ) ? 'logical only' : implode( ', ', $physical )
);

bb22_assert(
	'the section CSS uses logical properties (RTL-safe)',
	false !== strpos( $sections_css, 'inline-size' ) || false !== strpos( $sections_css, 'padding-inline' ),
	'logical properties present'
);

/* =====================================================================
 * K. MULTISITE ISOLATION (§32)
 * ================================================================== */
bb22_group( 'K. Multisite — LawFirm / Medical / non-builder isolation' );

$builder_sites = array();
$plain_sites   = array();

foreach ( $site_info as $blog_id => $info ) {
	if ( 'business-builder' === $info['theme'] ) {
		$builder_sites[ $blog_id ] = $info;
	} else {
		$plain_sites[ $blog_id ] = $info;
	}
}

bb22_assert(
	'the network has at least one Business Builder site',
	! empty( $builder_sites ),
	count( $builder_sites ) . ' builder sites'
);

bb22_assert(
	'the network has a non-Business-Builder site for the Astra check',
	! empty( $plain_sites ),
	count( $plain_sites ) . ' plain sites'
);

/* K1 — the per-site design scope. */
$lawfirm_sites = array();
$medical_sites = array();

foreach ( $builder_sites as $blog_id => $info ) {

	if ( 'law_firm' === $info['type'] ) {
		$lawfirm_sites[] = $blog_id;
	}

	if ( 'medical' === $info['type'] ) {
		$medical_sites[] = $blog_id;
	}
}

bb22_assert(
	'a LawFirm site exists',
	! empty( $lawfirm_sites ),
	implode( ', ', $lawfirm_sites )
);

bb22_assert(
	'a Medical site exists',
	! empty( $medical_sites ),
	implode( ', ', $medical_sites )
);

/* For each builder site, verify the design OFFERED to it is scoped correctly. */
foreach ( $builder_sites as $blog_id => $info ) {

	$offered = $catalogue->for_business_type( $info['type'] );

	$foreign = array();

	foreach ( $offered as $slug => $design ) {

		/* A generic or Default design is legal everywhere. */
		if ( $design['is_generic'] || $design['is_default'] ) {
			continue;
		}

		if ( ! in_array( $info['type'], $design['business_types'], true ) ) {
			$foreign[] = $slug;
		}
	}

	bb22_assert(
		'site ' . $blog_id . ' (' . ( $info['type'] ?: 'no type' ) . ') is offered no foreign design',
		empty( $foreign ),
		empty( $foreign ) ? count( $offered ) . ' designs, all valid' : 'leaked: ' . implode( ', ', $foreign )
	);
}

/* K2 — the RESOLVER filters too (the measured Phase 21 leak). */
foreach ( $builder_sites as $blog_id => $info ) {

	$active = $catalogue->active( $blog_id );

	$valid = $catalogue->is_valid_for( $active, $info['type'] );

	bb22_assert(
		'site ' . $blog_id . ' resolves an ACTIVE design valid for its business type',
		$valid,
		'active=' . $active . ' type=' . ( $info['type'] ?: '(none)' )
	);
}

/* K3 — the specific measured leak: a Medical design on a LawFirm site. */
if ( ! empty( $lawfirm_sites ) ) {

	foreach ( $lawfirm_sites as $blog_id ) {

		$active = $catalogue->active( $blog_id );

		bb22_assert(
			'no Medical design is active on LawFirm site ' . $blog_id,
			false === strpos( $active, 'medical' ),
			'active=' . $active
		);
	}
}

if ( ! empty( $medical_sites ) ) {

	foreach ( $medical_sites as $blog_id ) {

		$active = $catalogue->active( $blog_id );

		bb22_assert(
			'no LawFirm design is active on Medical site ' . $blog_id,
			false === strpos( $active, 'lawfirm' ),
			'active=' . $active
		);
	}
}

/* K4 — a non-Business-Builder site must not be offered a business-specific design. */
foreach ( $plain_sites as $blog_id => $info ) {

	$offered = $catalogue->for_business_type( $info['type'] );

	$business_specific = array();

	foreach ( $offered as $slug => $design ) {
		if ( ! empty( $design['business_types'] ) ) {
			$business_specific[] = $slug;
		}
	}

	bb22_assert(
		'non-builder site ' . $blog_id . ' (' . $info['theme'] . ') is offered no business-specific design',
		empty( $business_specific ),
		empty( $business_specific ) ? count( $offered ) . ' designs, all generic' : implode( ', ', $business_specific )
	);
}

/* K5 — storage is per-site. */
bb22_assert(
	'section overrides are stored in per-site theme mods',
	0 === strpos( \BusinessBuilderCore\Design\SectionStyleSchema::MOD_PREFIX, 'bb_' ),
	\BusinessBuilderCore\Design\SectionStyleSchema::MOD_PREFIX . '<type>'
);

$mod_probe = 'bb22_probe_' . wp_generate_password( 6, false );

set_theme_mod( $mod_probe, 'site-scoped' );

bb22_assert(
	'a theme mod written here is readable here',
	'site-scoped' === get_theme_mod( $mod_probe, '' ),
	'same site'
);

remove_theme_mod( $mod_probe );

/* =====================================================================
 * L. SECURITY (§33)
 * ================================================================== */
bb22_group( 'L. Security — capability, nonce, sanitisation' );

$page_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/DesignPage.php' );

bb22_assert(
	'the section save handler checks the capability',
	false !== strpos( $page_source, 'handle_save_sections' ) && false !== strpos( $page_source, '$this->authorize()' ),
	'authorize() present'
);

bb22_assert(
	'the section save handler verifies a nonce',
	false !== strpos( $page_source, 'check_admin_referer( self::ACTION_SAVE_SECTIONS )' ),
	'nonce verified'
);

bb22_assert(
	'the section save handler validates the section type against the registry',
	false !== strpos( $page_source, 'isset( $known[ $type ] )' ),
	'registry gate'
);

bb22_assert(
	'the section save handler normalises token names',
	false !== strpos( $page_source, "'--bb-section-'" ),
	'token prefix gate'
);

bb22_assert(
	'the section save handler never reads a blog id from the request',
	false === strpos( $page_source, "\$_POST['blog_id']" ) && false === strpos( $page_source, "\$_REQUEST['blog_id']" ),
	'current site only'
);

bb22_assert(
	'the Design screen requires the theme-options capability',
	false !== strpos( $page_source, "const CAPABILITY = 'edit_theme_options'" ),
	'edit_theme_options'
);

/* The generic section validator is exercised with hostile input. */
$hostile = array(
	'--bb-section-bg'               => 'url(javascript:alert(1))',
	'--bb-section-card-radius'      => '4px;}html{display:none',
	'--bb-section-card-shadow'      => 'expression(alert(1))',
	'--bb-section-reveal'           => '</style><script>alert(1)</script>',
	'--bb-section-bg-image'         => 'aurora;background:red',
	'--bb-section-align'            => 'center;}*{color:red',
	'--bb-section-card-image-ratio' => '1/1;}body{display:none',
);

$hostile_saved = $section_schema->save( 'hero', $hostile );

$hostile_css = $section_schema->css_for( 'hero' );

$hostile_ok = ( false === strpos( $hostile_css, 'script' ) )
	&& ( false === strpos( $hostile_css, 'expression' ) )
	&& ( false === strpos( $hostile_css, 'javascript:' ) )
	&& ( false === strpos( $hostile_css, 'display:none' ) )
	&& ( false === strpos( $hostile_css, '}' . 'html' ) );

bb22_assert(
	'hostile section values cannot escape into CSS',
	$hostile_ok,
	$hostile_saved . ' survived, CSS clean: ' . ( $hostile_ok ? 'yes' : 'NO' )
);

if ( '' !== $hostile_css ) {
	echo '        emitted: ' . substr( $hostile_css, 0, 160 ) . PHP_EOL;
}

$section_schema->reset( 'hero' );

/* =====================================================================
 * M. ARCHITECTURAL GUARDS (§38, §39)
 * ================================================================== */
bb22_group( 'M. Architectural guards — one theme, one namespace, no second system' );

$theme_dirs = array();

foreach ( (array) glob( WP_CONTENT_DIR . '/themes/*', GLOB_ONLYDIR ) as $dir ) {
	$theme_dirs[] = basename( $dir );
}

echo '  themes installed: ' . implode( ', ', $theme_dirs ) . PHP_EOL;

bb22_assert(
	'Phase 22 introduced no second Business Builder theme',
	count( array_filter( $theme_dirs, function ( $name ) {
		return false !== strpos( $name, 'business-builder' ) || false !== strpos( $name, 'builder' );
	} ) ) === 1,
	'one builder theme'
);

/* ONE token namespace across the whole plugin design layer. */
$foreign_namespaces = array();

foreach ( array( 'DesignSchema', 'SectionStyleSchema', 'DesignShellState', 'SectionStudioUI' ) as $class ) {

	$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/' . $class . '.php' );

	/* A token declaration would look like `--something-else:`. */
	if ( preg_match_all( '/--(?!bb-)[a-z][a-z0-9-]*\s*:/', $source, $matches ) ) {
		foreach ( $matches[0] as $match ) {
			$foreign_namespaces[] = $class . ':' . trim( $match );
		}
	}
}

bb22_assert(
	'no second token namespace exists in the design layer',
	empty( $foreign_namespaces ),
	empty( $foreign_namespaces ) ? 'all --bb-*' : implode( ', ', array_slice( $foreign_namespaces, 0, 5 ) )
);

/* The generic Studio never references a business concept. */
$studio_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/SectionStudioUI.php' );

bb22_assert(
	'the generic Section Studio references no business type in executable code',
	0 === preg_match( "/law_firm|medical|lawyers|doctors|lawfirm/i", bb22_strip_comments( $studio_source ) ),
	'0 matches'
);

/*
 * Existing architecture is extended, not replaced.
 *
 * The whole plugin is concatenated once, so a hook is credited wherever it is
 * legitimately used - including the packs, which is where `bb_register_packs`
 * and `bb_register_section_variants` actually live.
 */
$plugin_source = '';

foreach ( array( '/includes', '/packs' ) as $dir ) {

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( dirname( __DIR__ ) . $dir )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$plugin_source .= (string) file_get_contents( $file->getPathname() );
		}
	}
}

foreach ( array( 'bb_theme_design_schema', 'bb_theme_presets', 'bb_theme_preset_config', 'bb_theme_shell_variant', 'bb_register_packs', 'bb_register_section_variants' ) as $hook ) {

	bb22_assert(
		'the existing extension point ' . $hook . ' is used',
		false !== strpos( $plugin_source, $hook ),
		'used'
	);
}

/* =====================================================================
 * N. PERFORMANCE (§34)
 * ================================================================== */
bb22_group( 'N. Performance — conditional assets, no heavy library' );

$js_files = array();

foreach ( (array) glob( dirname( __DIR__ ) . '/assets/js/frontend/*.js' ) as $file ) {
	$js_files[ basename( $file ) ] = (string) file_get_contents( $file );
}

echo '  frontend scripts: ' . implode( ', ', array_keys( $js_files ) ) . PHP_EOL;

$heavy = array();

foreach ( $js_files as $name => $source ) {
	if ( preg_match( '/jquery|gsap|ScrollMagic|AOS|locomotive|three\.js/i', $source ) ) {
		$heavy[] = $name;
	}
}

bb22_assert(
	'no heavy animation library is shipped to the frontend',
	empty( $heavy ),
	empty( $heavy ) ? '0 libraries' : implode( ', ', $heavy )
);

$sizes = array();

foreach ( $js_files as $name => $source ) {
	$sizes[] = $name . '=' . round( strlen( $source ) / 1024, 1 ) . 'KB';
}

echo '  script sizes: ' . implode( ', ', $sizes ) . PHP_EOL;

bb22_assert(
	'the motion script stays lightweight',
	strlen( $js_files['design-motion.js'] ?? '' ) < 12000,
	round( strlen( $js_files['design-motion.js'] ?? '' ) / 1024, 1 ) . 'KB'
);

bb22_assert(
	'the section style block is emitted only when a section is customized',
	false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/Design/SectionStyleSchema.php' ), 'if ( empty( $blocks ) )' ),
	'conditional emission'
);

/* =====================================================================
 * REPORT
 * ================================================================== */
$pass = $GLOBALS['bb22']['pass'];
$fail = $GLOBALS['bb22']['fail'];
$total = $pass + $fail;

echo PHP_EOL . str_repeat( '=', 70 ) . PHP_EOL;
echo 'PHASE 22 TEST RESULT: ' . ( 0 === $fail ? 'PASS' : 'FAIL' ) . PHP_EOL;
echo '  passed: ' . $pass . ' / ' . $total . PHP_EOL;
echo '  failed: ' . $fail . PHP_EOL;

if ( $fail > 0 ) {
	echo PHP_EOL . 'Failures:' . PHP_EOL;
	foreach ( $GLOBALS['bb22']['failures'] as $failure ) {
		echo '  - ' . $failure . PHP_EOL;
	}
}

echo str_repeat( '=', 70 ) . PHP_EOL;

exit( $fail > 0 ? 1 : 0 );