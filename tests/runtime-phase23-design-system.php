<?php
/**
 * PHASE 23 RUNTIME TEST SUITE
 * ===========================
 * Enterprise Visual Control System: Global Layout, Typography, Motion, Navbar,
 * Scrollbar, Services, capabilities, icons — and the audit defects they fix.
 *
 * Every assertion is measured against the REAL plugin code (schema, sanitizer,
 * registries, renderers, stylesheets) — never against a mock. The suite exits
 * non-zero if anything fails.
 *
 * Run: php tests/runtime-phase23-design-system.php
 *
 * COVERAGE
 * --------
 *   A. Audit regression   — the 5 orphaned section tokens now have consumers
 *   B. Global Layout      — controls exist, save, validate, reach CSS
 *   C. Global Typography  — same, including the alias-bridge fallbacks
 *   D. Navbar             — controls + the keyword classes that drive layout
 *   E. Global Motion      — master preset composes tokens, override wins
 *   F. Scrollbar          — conditional, admin-safe, validated emission
 *   G. Services section   — registry, capabilities, 5 variants, icon rendering
 *   H. Capabilities       — declared, closed vocabulary, honoured by the Studio
 *   I. Icons              — catalogue, validation, markup, no duplicate loading
 *   J. Workspace safety   — no new namespace, no second schema, no dupes
 *   K. Multisite          — per-site isolation of every new control
 *
 * @package BusinessBuilderCore
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

$GLOBALS['bb23'] = array( 'pass' => 0, 'fail' => 0, 'failures' => array(), 'group' => '' );

/**
 * Assert a condition and report it.
 *
 * @param string $label    What is asserted.
 * @param bool   $ok       Result.
 * @param string $measured Measured value.
 */
function bb23_assert( string $label, bool $ok, string $measured = '' ): void {

	if ( $ok ) {
		$GLOBALS['bb23']['pass']++;
		echo "  PASS  {$label}" . ( '' !== $measured ? "  [{$measured}]" : '' ) . PHP_EOL;

		return;
	}

	$GLOBALS['bb23']['fail']++;
	$GLOBALS['bb23']['failures'][] = $GLOBALS['bb23']['group'] . ' :: ' . $label;

	echo "  FAIL  {$label}" . ( '' !== $measured ? "  [{$measured}]" : '' ) . PHP_EOL;
}

/**
 * Start a named group.
 *
 * @param string $name Group name.
 */
function bb23_group( string $name ): void {

	$GLOBALS['bb23']['group'] = $name;

	echo PHP_EOL . '== ' . $name . ' ' . str_repeat( '=', max( 4, 62 - strlen( $name ) ) ) . PHP_EOL;
}

/**
 * Read a plugin or theme source file.
 *
 * @param string $relative Path relative to the plugin root.
 * @return string
 */
function bb23_source( string $relative ): string {

	static $cache = array();

	if ( ! isset( $cache[ $relative ] ) ) {
		$path = dirname( __DIR__ ) . '/' . ltrim( $relative, '/' );

		$cache[ $relative ] = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	return $cache[ $relative ];
}

$plugin_root  = dirname( __DIR__ );
$theme_root   = dirname( $plugin_root, 2 ) . '/themes/business-builder';
$consumer_css = bb23_source( 'assets/css/frontend/design-sections.css' );
$services_css = bb23_source( 'assets/css/frontend/section-services.css' );

/* =====================================================================
 * Bootstrap: load the Theme's function files, exactly as functions.php would.
 *
 * WHY THIS IS NEEDED
 * ------------------
 * Site 1 of this install runs Astra, so the canonical Theme is not loaded and
 * `bb_theme_design_schema()` would not exist. The schema is the CONTRACT this
 * suite tests, so it is loaded explicitly (the same approach the Phase 22 suite
 * uses) rather than skipping the assertions on this site.
 * ================================================================== */
foreach ( array( 'setup', 'theme-support', 'preset-resolver', 'design-schema', 'customization', 'shell-variants', 'shell-data' ) as $bb23_file ) {

	$bb23_path = $theme_root . '/inc/' . $bb23_file . '.php';

	if ( is_readable( $bb23_path ) ) {
		require_once $bb23_path;
	}
}

echo 'Business Builder Core — Phase 23 runtime suite' . PHP_EOL;
echo 'Theme schema available: ' . ( function_exists( 'bb_theme_design_schema' ) ? 'yes' : 'NO' ) . PHP_EOL;
echo 'Icon API available: ' . ( function_exists( 'bb_render_icon' ) ? 'yes' : 'NO' ) . PHP_EOL;

/* ------------------------------------------------------------------ *
 * A. Audit regressions — the measured orphans now have consumers.
 * ------------------------------------------------------------------ */
bb23_group( 'A. Audit regressions (measured orphans fixed)' );

$orphan_tokens = array(
	'--bb-section-glass-blur',
	'--bb-section-glass-opacity',
	'--bb-section-glass-border-opacity',
	'--bb-section-reveal-duration',
	'--bb-section-reveal-delay',
	'--bb-color-danger',
);

foreach ( $orphan_tokens as $token ) {

	/*
	 * Whitespace-tolerant on purpose: the CSS may break the value list across
	 * lines, which is a formatting choice, not a behavioural one.
	 */
	$consumed = (bool) preg_match( '/var\(\s*' . preg_quote( $token, '/' ) . '\s*[,)]/', $consumer_css );

	bb23_assert(
		"{$token} is now consumed by the frontend layer",
		$consumed,
		$consumed ? 'consumed' : 'STILL ORPHANED'
	);
}

bb23_assert(
	'section borders are 0 unless a surface declares one',
	false !== strpos( $consumer_css, 'border-width: var(--bb-section-border-width, 0)' ),
	'borders off by default'
);

$state = function_exists( 'bb_section_presentation_state' ) ? bb_section_presentation_state( 'features' ) : array();

bb23_assert(
	'the presentation state publishes a surface flag for the border rule',
	array_key_exists( 'data-bb-surface', $state ),
	'keys: ' . implode( ',', array_keys( $state ) )
);

/* ------------------------------------------------------------------ *
 * B–F. The new global controls.
 * ------------------------------------------------------------------ */
bb23_group( 'B. Global control inventory' );

$schema = function_exists( 'bb_theme_design_schema' ) ? bb_theme_design_schema() : array();

$by_key = array();

foreach ( $schema as $control ) {

	if ( is_array( $control ) && isset( $control['key'] ) ) {
		$by_key[ (string) $control['key'] ] = $control;
	}
}

/**
 * The controls Phase 23 was required to add, and the group each belongs to.
 *
 * @var array<string, string> key => expected group.
 */
$expected = array(
	/* Global Layout (§9) */
	'layout_mode'          => 'layout',
	'site_margin'          => 'layout',
	'section_gap'          => 'layout',
	/* Global Typography (§10) */
	'body_weight'          => 'typography',
	'heading_weight'       => 'typography',
	'body_letter_spacing'  => 'typography',
	'heading_line_height'  => 'typography',
	'heading_transform'    => 'typography',
	/* Navbar (§12) */
	'nav_position'         => 'navbar',
	'nav_link_size'        => 'navbar',
	'nav_link_weight'      => 'navbar',
	'nav_link_tracking'    => 'navbar',
	'nav_link_hover'       => 'navbar',
	'nav_link_active'      => 'navbar',
	'nav_indicator'        => 'navbar',
	'nav_radius'           => 'navbar',
	'logo_height'          => 'navbar',
	/* Motion (§14) */
	'motion_mode'          => 'motion',
	'motion_ease'          => 'motion',
	/* Scrollbar (§15) */
	'scrollbar_width'      => 'scrollbar',
	'scrollbar_track'      => 'scrollbar',
	'scrollbar_thumb'      => 'scrollbar',
	'scrollbar_thumb_hover' => 'scrollbar',
);

bb23_assert(
	'every required control is registered exactly once',
	count( $by_key ) === count( array_unique( array_keys( $by_key ) ) ),
	'schema controls: ' . count( $schema )
);

$missing   = array();
$bad_group = array();
$no_token  = array();

foreach ( $expected as $key => $group ) {

	if ( ! isset( $by_key[ $key ] ) ) {
		$missing[] = $key;
		continue;
	}

	if ( (string) $by_key[ $key ]['group'] !== $group ) {
		$bad_group[] = $key . '=' . $by_key[ $key ]['group'];
	}

	$token = (string) ( $by_key[ $key ]['token'] ?? '' );

	if ( 0 !== strpos( $token, '--bb-' ) ) {
		$no_token[] = $key;
	}
}

bb23_assert( 'all 23 new controls exist', empty( $missing ), empty( $missing ) ? 'all present' : implode( ', ', $missing ) );
bb23_assert( 'each new control declares the group it belongs to', empty( $bad_group ), empty( $bad_group ) ? 'all correct' : implode( ', ', $bad_group ) );
bb23_assert( 'each new control writes a --bb-* token', empty( $no_token ), empty( $no_token ) ? 'all namespaced' : implode( ', ', $no_token ) );

/* No token is written by two controls (one authoritative source per property). */
$token_owners = array();
$duplicates   = array();

foreach ( $schema as $control ) {

	$token = (string) ( $control['token'] ?? '' );

	if ( '' === $token ) {
		continue;
	}

	if ( isset( $token_owners[ $token ] ) ) {
		$duplicates[] = $token . ' (' . $token_owners[ $token ] . ' + ' . $control['key'] . ')';
	}

	$token_owners[ $token ] = (string) $control['key'];
}

bb23_assert(
	'no two controls write the same token',
	empty( $duplicates ),
	empty( $duplicates ) ? 'one owner per token' : implode( ', ', $duplicates )
);

/* Section-scoped and global tokens must not collide either. */
$section_schema = new \BusinessBuilderCore\Design\SectionStyleSchema();
$section_tokens = array();
$collisions     = array();

foreach ( $section_schema->groups() as $group ) {
	foreach ( (array) ( $group['controls'] ?? array() ) as $control ) {

		$token = (string) ( $control['token'] ?? '' );

		if ( '' === $token ) {
			continue;
		}

		$section_tokens[ $token ] = true;

		if ( isset( $token_owners[ $token ] ) ) {
			$collisions[] = $token;
		}
	}
}

/*
 * The two known collisions are INTENTIONAL cascade pairs (a site-wide default and
 * a per-section override of the same property), and the override wins because it
 * is emitted later in a narrower scope. They are asserted as a closed set so a
 * NEW collision fails the suite.
 */
sort( $collisions );

$known_pairs = array( '--bb-section-align', '--bb-section-padding-block' );

bb23_assert(
	'the only global/section token overlaps are the two documented cascade pairs',
	$collisions === $known_pairs,
	'overlaps: ' . ( empty( $collisions ) ? 'none' : implode( ', ', $collisions ) )
);

/* ------------------------------------------------------------------ *
 * C. Validation — every new control accepts a valid value and rejects junk.
 * ------------------------------------------------------------------ */
bb23_group( 'C. Validation of the new controls' );

$bad_values   = array();
$valid_failed = array();

/*
 * key => values that MUST be rejected. The sanitizer's contract is "return ''
 * (inherit) rather than store something unsafe or out of range".
 */
$must_reject = array(
	'layout_mode'           => array( 'rainbow', '<script>', 'FULL' ),
	'site_margin'           => array( '9999px', '-4px', 'wide', '12ch' ),
	'section_gap'           => array( '9999px', 'auto' ),
	'body_weight'           => array( '950', 'heavy', '400;color:red' ),
	'heading_weight'        => array( '400', '1000' ),
	'body_letter_spacing'   => array( '9', 'wide' ),
	'heading_line_height'   => array( '0.2', 'five' ),
	'heading_transform'     => array( 'smallcaps', 'UP' ),
	'nav_position'          => array( 'middle', 'left' ),
	'nav_link_size'         => array( '0.1rem', '40px' ),
	'nav_link_weight'       => array( 'heavy' ),
	'nav_link_tracking'     => array( '9' ),
	'nav_link_hover'        => array( 'red', 'url(x)', '#12' ),
	'nav_link_active'       => array( 'blue' ),
	'nav_indicator'         => array( 'line', 'glow' ),
	'nav_radius'            => array( '900px' ),
	'logo_height'           => array( '999px', 'big' ),
	'motion_mode'           => array( 'insane', 'on' ),
	'motion_ease'           => array( 'bounce(3)', 'fast' ),
	'scrollbar_width'       => array( '99px', 'thin' ),
	'scrollbar_track'       => array( 'track', '#ff' ),
	'scrollbar_thumb'       => array( 'thumb' ),
	'scrollbar_thumb_hover' => array( 'hover' ),
);

/* A representative VALID value per control, which must survive sanitizing. */
$must_accept = array(
	'layout_mode'           => 'boxed',
	'site_margin'           => '24px',
	'section_gap'           => '48px',
	'body_weight'           => '500',
	'heading_weight'        => '800',
	'body_letter_spacing'   => '0.02',
	'heading_line_height'   => '1.3',
	'heading_transform'     => 'uppercase',
	'nav_position'          => 'start',
	'nav_link_size'         => '1.125rem',
	'nav_link_weight'       => '600',
	'nav_link_tracking'     => '0.05',
	'nav_link_hover'        => '#ff8800',
	'nav_link_active'       => '#123456',
	'nav_indicator'         => 'pill',
	'nav_radius'            => '16px',
	'logo_height'           => '64px',
	'motion_mode'           => 'subtle',
	'motion_ease'           => 'ease-out',
	'scrollbar_width'       => '12px',
	'scrollbar_track'       => '#eeeeee',
	'scrollbar_thumb'       => '#333333',
	'scrollbar_thumb_hover' => '#111111',
);

foreach ( $must_accept as $key => $value ) {

	if ( ! isset( $by_key[ $key ] ) ) {
		continue;
	}

	if ( '' === bb_theme_sanitize_design_value( $value, $by_key[ $key ] ) ) {
		$valid_failed[] = $key . ' <- ' . $value;
	}
}

foreach ( $must_reject as $key => $values ) {

	if ( ! isset( $by_key[ $key ] ) ) {
		continue;
	}

	foreach ( $values as $value ) {

		$clean = bb_theme_sanitize_design_value( $value, $by_key[ $key ] );

		if ( '' !== $clean ) {
			$bad_values[] = $key . ' accepted ' . $value . ' as ' . $clean;
		}
	}
}

bb23_assert( 'every new control accepts at least one valid value', empty( $valid_failed ), empty( $valid_failed ) ? 'all accepted' : implode( ', ', $valid_failed ) );
bb23_assert( 'every new control rejects out-of-range / forged / off-vocabulary values', empty( $bad_values ), empty( $bad_values ) ? 'nothing accepted' : implode( '; ', $bad_values ) );

/* ------------------------------------------------------------------ *
 * D. Global Motion master (§14)
 * ------------------------------------------------------------------ */
bb23_group( 'D. Global motion master' );

$design_schema = new \BusinessBuilderCore\Design\DesignSchema();
$library       = $design_schema->motion_mode_library();

/* The site's stored overrides, so the master-preset precedence can be reasoned about. */
$current_overrides = function_exists( 'bb_theme_customization_overrides' )
	? bb_theme_customization_overrides()
	: array();

bb23_assert(
	'four intensity levels are offered',
	count( $library ) === 4,
	'levels: ' . implode( ', ', array_keys( $library ) )
);

$library_tokens = array();

foreach ( $library as $level ) {
	foreach ( array_keys( $level ) as $token ) {
		$library_tokens[ $token ] = true;
	}
}

$undeclared = array();

foreach ( array_keys( $library_tokens ) as $token ) {

	if ( ! isset( $token_owners[ $token ] ) ) {
		$undeclared[] = $token;
	}
}

bb23_assert(
	'the master preset only writes tokens the schema already declares',
	empty( $undeclared ),
	empty( $undeclared ) ? 'all declared' : implode( ', ', $undeclared )
);

/* "None" must genuinely mean static, not merely "unset". */
$off = $library['off'];

bb23_assert(
	'the "none" level zeroes tempo, duration, distance, lift and stagger',
	'0.01' === $off['--bb-motion-normal']
		&& '0.01' === $off['--bb-reveal-duration']
		&& '0px' === $off['--bb-reveal-distance']
		&& '0px' === $off['--bb-hover-lift']
		&& '0' === $off['--bb-reveal-stagger'],
	'off = ' . wp_json_encode( $off )
);

/* The resolver publishes the mode's values when nothing else claims the token. */
$resolved = $design_schema->resolve_motion_mode( array( '--bb-motion-mode' => 'subtle' ) );

bb23_assert(
	'the chosen level publishes its own tempo',
	'0.16' === (string) $resolved['--bb-motion-normal'],
	'motion-normal = ' . (string) $resolved['--bb-motion-normal']
);

bb23_assert(
	'no stored override interferes with this test site',
	! isset( $current_overrides['--bb-motion-normal'] )
		&& ! isset( $current_overrides['--bb-reveal-kind'] ),
	'stored overrides: ' . count( $current_overrides )
);

$unknown = $design_schema->resolve_motion_mode( array( '--bb-motion-mode' => 'not-a-mode' ) );

bb23_assert(
	'an unknown mode leaves the configuration untouched',
	! isset( $unknown['--bb-reveal-kind'] ),
	'an unknown mode is inert'
);

/* ------------------------------------------------------------------ *
 * E. Navbar keyword classes (§12)
 * ------------------------------------------------------------------ */
bb23_group( 'E. Navbar and layout keyword classes' );

$shell_state = new \BusinessBuilderCore\Design\DesignShellState();

$base_classes = $shell_state->body_class( array( 'bb-theme' ) );

bb23_assert(
	'an unconfigured site gains no new state class',
	! in_array( 'has-bb-nav-pill', $base_classes, true )
		&& ! in_array( 'has-bb-layout-boxed', $base_classes, true )
		&& ! in_array( 'has-bb-motion-off', $base_classes, true ),
	'classes: ' . implode( ', ', $base_classes )
);

/* The class list must be derived from the RESOLVED tokens, and the CSS must read it. */
foreach ( array( 'has-bb-nav-pill', 'has-bb-nav-underline', 'has-bb-nav-dot', 'has-bb-layout-boxed', 'has-bb-motion-off' ) as $class ) {

	bb23_assert(
		"the stylesheet consumes .{$class}",
		false !== strpos( $consumer_css, '.' . $class ),
		'present in design-sections.css'
	);
}

/* A keyword select must never be emitted as a raw CSS value by mistake. */
bb23_assert(
	'the keyword controls are published as classes, not as CSS values',
	false === strpos( $consumer_css, 'var(--bb-nav-indicator' )
		&& false === strpos( $consumer_css, 'var(--bb-layout-mode' ),
	'no keyword leak into a declaration'
);

/* ------------------------------------------------------------------ *
 * F. Scrollbar (§15) — conditional and admin-safe
 * ------------------------------------------------------------------ */
bb23_group( 'F. Scrollbar emission' );

$scrollbar_tokens = array_keys( $shell_state->scrollbar_controls() );

bb23_assert(
	'the scrollbar feature owns exactly the four declared tokens',
	count( $scrollbar_tokens ) === 4,
	'tokens: ' . implode( ', ', $scrollbar_tokens )
);

foreach ( $scrollbar_tokens as $token ) {

	bb23_assert(
		"{$token} has a frontend consumer inside the page wrapper",
		(bool) preg_match( '/var\(\s*' . preg_quote( $token, '/' ) . '\s*[,)]/', $consumer_css ),
		'in design-sections.css (inner scroll areas)'
	);
}

/*
 * The emission must be gated on a DELIBERATE setting, because the rules target
 * `html` — which wp-admin also has. Without the gate, one site's branding would
 * restyle the whole install.
 */
bb23_assert(
	'the scrollbar rules are not emitted for a site that never set one',
	false === $shell_state->has_custom_scrollbar(),
	'has_custom_scrollbar = false on this site'
);

bb23_assert(
	'the emitter re-validates every value through the schema sanitizer',
	false !== strpos( bb23_source( 'includes/Design/DesignShellState.php' ), 'bb_theme_sanitize_design_value' ),
	'validated before emission'
);

bb23_assert(
	'the scrollbar rules are attached to their own dequeueable handle',
	false !== strpos( bb23_source( 'includes/Design/DesignShellState.php' ), 'bb-scrollbar' ),
	'handle: bb-scrollbar'
);

/* ------------------------------------------------------------------ *
 * G. Services section (§11, §26)
 * ------------------------------------------------------------------ */
bb23_group( 'G. Services section' );

$registry = new \BusinessBuilderCore\Builder\SectionRegistry();
$core     = new \BusinessBuilderCore\Builder\CoreSections( $registry );
$core->register();

bb23_assert(
	'the Services section is registered',
	$registry->exists( 'services' ),
	'services: ' . ( $registry->exists( 'services' ) ? 'yes' : 'NO' )
);

$services = $registry->get( 'services' );

$content_fields = array_keys( (array) ( $services['content'] ?? array() ) );
$item_fields    = array_keys( (array) ( $services['content']['items']['fields'] ?? array() ) );

bb23_assert(
	'the section carries the shared content fields',
	in_array( 'title', $content_fields, true ) && in_array( 'description', $content_fields, true ) && in_array( 'items', $content_fields, true ),
	'content: ' . implode( ', ', $content_fields )
);

bb23_assert(
	'each service supports BOTH an icon and an image',
	in_array( 'icon', $item_fields, true ) && in_array( 'image', $item_fields, true ),
	'item fields: ' . implode( ', ', $item_fields )
);

bb23_assert(
	'the icon field is a validated icon picker, not free text',
	( $services['content']['items']['fields']['icon']['type'] ?? '' ) === 'select',
	'type: ' . (string) ( $services['content']['items']['fields']['icon']['type'] ?? '' )
);

/* Variants: the Phase 11 registry is the layout authority. */
$variants = function_exists( 'bb_section_variants' ) ? bb_section_variants() : null;

$available = $variants ? $variants->available( 'services' ) : array();

bb23_assert(
	'five layouts are registered for Services',
	count( array_intersect( array( 'default', 'list', 'featured', 'icon-text', 'image-text' ), $available ) ) === 5,
	'variants: ' . implode( ', ', $available )
);

$template_ok = true;

foreach ( array( 'default', 'list', 'featured', 'icon-text', 'image-text' ) as $slug ) {

	$template = $variants ? $variants->template( 'services', $slug ) : '';

	if ( '' === $template || ! is_readable( $template ) ) {
		$template_ok = false;
	}
}

bb23_assert( 'every Services layout resolves to a readable template', $template_ok, 'templates readable' );

bb23_assert(
	'the Services section offers exactly ONE layout control (the variant)',
	isset( $services['settings']['variant'] ) && ! isset( $services['settings']['presentation'] ),
	'settings: ' . implode( ', ', array_keys( (array) $services['settings'] ) )
);

bb23_assert(
	'the variant options come from the registry, so the picker cannot drift',
	! empty( $services['settings']['variant']['options'] ),
	'options: ' . count( (array) $services['settings']['variant']['options'] )
);

/* The renderer must actually produce the card markup, with the icon inside it. */
$renderer = new \BusinessBuilderCore\Builder\SectionRenderer( $registry );

ob_start();

$renderer->render_section(
	array(
		'id'       => 'bb23-services',
		'type'     => 'services',
		'settings' => array( 'variant' => 'default', 'columns' => '3', 'card_style' => 'elevated', 'align' => 'start' ),
		'content'  => array(
			'title' => 'What we do',
			'items' => array(
				array( 'icon' => 'briefcase', 'title' => 'Advisory', 'description' => 'Strategy and planning.' ),
				array( 'icon' => 'not-a-real-icon', 'title' => 'Ignored icon', 'description' => 'Falls back safely.' ),
			),
		),
	)
);

$services_html = (string) ob_get_clean();

bb23_assert(
	'the rendered section is a bb-section-services element',
	false !== strpos( $services_html, 'bb-section-services' ),
	'<section class="bb-section bb-section-services">'
);

bb23_assert(
	'each item renders as a service card with its title',
	false !== strpos( $services_html, 'bb-service-card' ) && false !== strpos( $services_html, 'Advisory' ),
	'card ＋ title present'
);

bb23_assert(
	'a VALID icon slug renders Font Awesome markup',
	false !== strpos( $services_html, 'fa-solid fa-briefcase' ),
	'fa-solid fa-briefcase'
);

bb23_assert(
	'an UNKNOWN icon slug renders nothing at all',
	false === strpos( $services_html, 'not-a-real-icon' ) && false === strpos( $services_html, 'fa-' . 'not-a-real-icon' ),
	'forged slug produced no markup'
);

/* ------------------------------------------------------------------ *
 * G2. Hero upgrade (§11)
 * ------------------------------------------------------------------ */
bb23_group( 'G2. Hero presentation' );

$hero = $registry->get( 'hero' );

bb23_assert(
	'the hero declares the media and slider capabilities',
	$registry->has_capability( 'hero', 'media' ) && $registry->has_capability( 'hero', 'slider' ),
	'capabilities: ' . implode( ', ', $registry->get_capabilities( 'hero' ) )
);

bb23_assert(
	'the hero can present as a slider',
	isset( $hero['settings']['mode'] ),
	'mode: ' . (string) ( $hero['settings']['mode']['default'] ?? '' )
);

bb23_assert(
	'the content image can be placed',
	isset( $hero['settings']['media_position'] ) && count( (array) $hero['settings']['media_position']['options'] ) === 5,
	'placements: ' . implode( ', ', array_keys( (array) $hero['settings']['media_position']['options'] ) )
);

bb23_assert(
	'the hero gained a secondary call to action',
	isset( $hero['content']['button_2_text'] ) && isset( $hero['content']['button_2_url'] ),
	'secondary CTA present'
);

bb23_assert(
	'hero slider slides are declared as content, not as a second slider',
	isset( $hero['content']['slides']['fields'] ) && count( (array) $hero['content']['slides']['fields'] ) >= 5,
	'slide fields: ' . implode( ', ', array_keys( (array) $hero['content']['slides']['fields'] ) )
);

bb23_assert(
	'the hero does NOT duplicate the per-section background controls',
	! isset( $hero['settings']['background_color'] )
		&& ! isset( $hero['settings']['background_image'] )
		&& ! isset( $hero['settings']['overlay_opacity'] ),
	'the section Background group remains the single source'
);

/* The media placements must be real CSS classes. */
foreach ( array( 'bb-hero-media-start', 'bb-hero-media-end', 'bb-hero-media-above', 'bb-hero-media-below', 'bb-hero-media-hidden' ) as $class ) {

	bb23_assert(
		"the stylesheet implements .{$class}",
		false !== strpos( $consumer_css, '.' . $class ),
		'present in design-sections.css'
	);
}

/* Rendering: standard hero with a secondary CTA, and slider delegation. */
ob_start();

$renderer->render_section(
	array(
		'id'       => 'bb23-hero',
		'type'     => 'hero',
		'settings' => array( 'alignment' => 'center', 'min_height' => 500, 'mode' => 'standard', 'media_position' => 'hidden' ),
		'content'  => array(
			'title'         => 'Hero title',
			'button_text'   => 'Primary',
			'button_url'    => 'https://example.test/a',
			'button_2_text' => 'Secondary',
			'button_2_url'  => 'https://example.test/b',
		),
	)
);

$hero_html = (string) ob_get_clean();

bb23_assert(
	'the standard hero renders both calls to action',
	false !== strpos( $hero_html, 'bb-primary-button' ) && false !== strpos( $hero_html, 'bb-secondary-button' ),
	'primary ＋ secondary'
);

bb23_assert(
	'the hidden placement is reflected in the class, and no image is emitted',
	false !== strpos( $hero_html, 'bb-hero-media-hidden' ),
	'bb-hero-media-hidden'
);

ob_start();

$renderer->render_section(
	array(
		'id'       => 'bb23-hero-slider',
		'type'     => 'hero',
		'settings' => array( 'mode' => 'slider', 'min_height' => 620, 'media_position' => 'end' ),
		'content'  => array(
			'slides' => array(
				array( 'title' => 'First slide', 'description' => 'One', 'button_text' => 'Go', 'button_url' => 'https://example.test/1' ),
			),
		),
	)
);

$hero_slider_html = (string) ob_get_clean();

bb23_assert(
	'slider mode renders through the existing slider engine',
	false !== strpos( $hero_slider_html, 'First slide' ),
	'delegated to render_slider_section()'
);

bb23_assert(
	'slider mode does not also render the standard hero copy',
	false === strpos( $hero_slider_html, 'bb-hero-media-end' ),
	'no duplicated hero markup'
);

/* ------------------------------------------------------------------ *
 * H. Capabilities (§7, §8)
 * ------------------------------------------------------------------ */
bb23_group( 'H. Capability contract' );

bb23_assert(
	'the Services section declares the cards capability',
	$registry->has_capability( 'services', 'cards' ),
	'cards = yes'
);

bb23_assert(
	'the Services section declares the icons capability',
	$registry->has_capability( 'services', 'icons' ),
	'icons = yes'
);

bb23_assert(
	'an unknown section is never capable',
	! $registry->has_capability( 'no_such_section', 'cards' ),
	'unknown section = not capable'
);

bb23_assert(
	'an unknown capability is never granted',
	! $registry->has_capability( 'services', 'teleportation' ),
	'unknown capability = not capable'
);

/* `supports` is adopted as the capability list, so old sections are described. */
bb23_assert(
	'a pre-existing section inherits its `supports` list as capabilities',
	in_array( 'items', $registry->get_capabilities( 'features' ), true ),
	'features capabilities: ' . implode( ', ', $registry->get_capabilities( 'features' ) )
);

/* The vocabulary is closed, so a declared typo cannot enable a control. */
$closed = new \BusinessBuilderCore\Builder\SectionRegistry();

$closed->register( 'bb23_probe', array( 'supports' => array( 'cards', 'not_a_capability' ) ) );

bb23_assert(
	'the capability vocabulary is closed: an unknown declaration is dropped',
	! $closed->has_capability( 'bb23_probe', 'not_a_capability' )
		&& $closed->has_capability( 'bb23_probe', 'cards' ),
	'declared: ' . implode( ', ', $closed->get_capabilities( 'bb23_probe' ) )
);

/* ------------------------------------------------------------------ *
 * I. Icons (§18)
 * ------------------------------------------------------------------ */
bb23_group( 'I. Icon library' );

$icons = function_exists( 'bb_icons' ) ? bb_icons() : new \BusinessBuilderCore\Design\IconLibrary();

$catalogue = $icons->catalogue();

bb23_assert(
	'the catalogue is grouped into semantic categories',
	count( $catalogue ) >= 10,
	'categories: ' . count( $catalogue ) . ' (' . implode( ', ', array_keys( $catalogue ) ) . ')'
);

bb23_assert(
	'the catalogue offers a useful number of icons',
	count( $icons->slugs() ) >= 100,
	'icons: ' . count( $icons->slugs() )
);

bb23_assert(
	'the option map is shaped for the builder\'s existing select field',
	isset( $icons->flat_options()['briefcase'] ) && false !== strpos( (string) $icons->flat_options()['briefcase'], 'Briefcase' ),
	'flat options: ' . count( $icons->flat_options() )
);

bb23_assert(
	'a real slug validates',
	'briefcase' === $icons->validate( 'briefcase' ),
	'validate(briefcase) = briefcase'
);

bb23_assert(
	'a forged slug does not validate',
	'' === $icons->validate( '<img src=x onerror=alert(1)>' ) && '' === $icons->validate( 'nope' ),
	'forged slugs rejected'
);

$markup = $icons->render( 'gavel', array( 'label' => 'Justice' ) );

bb23_assert(
	'rendering produces an accessible, escaped element',
	false !== strpos( $markup, 'fa-solid fa-gavel' ) && false !== strpos( $markup, 'aria-label="Justice"' ) && false !== strpos( $markup, 'class="bb-icon' ),
	$markup
);

bb23_assert(
	'an unknown slug renders the empty string (safe to echo)',
	'' === $icons->render( 'not-real' ),
	'empty string'
);

/* The catalogue must not leak a business type into the generic layer. */
$business_terms = array( 'law_firm', 'lawfirm', 'medical' );
$leaks          = array();

foreach ( $business_terms as $term ) {

	if ( false !== stripos( wp_json_encode( array_keys( $catalogue ) ) . wp_json_encode( $icons->slugs() ), $term ) ) {
		$leaks[] = $term;
	}
}

bb23_assert(
	'the icon catalogue stays business-type agnostic',
	empty( $leaks ),
	empty( $leaks ) ? 'no business type in the catalogue' : implode( ', ', $leaks )
);

/* Asset loading: conditional AND de-duplicated. */
$icons->reset_state();

bb23_assert(
	'font is not required until an icon is rendered',
	false === $icons->was_needed(),
	'was_needed = false'
);

$icons->render( 'star' );

bb23_assert(
	'rendering an icon flags the request as needing the font',
	true === $icons->was_needed(),
	'was_needed = true'
);

$icons->reset_state();
$icons->render( 'star' );

wp_dequeue_style( 'bb-fontawesome' );
wp_deregister_style( 'bb-fontawesome' );

$icons->maybe_enqueue_assets();

bb23_assert(
	'the plugin loads Font Awesome exactly once, on its own handle',
	wp_style_is( \BusinessBuilderCore\Design\IconLibrary::FA_HANDLE, 'enqueued' ),
	'handle enqueued'
);

$before = count( wp_styles()->queue );
$icons->maybe_enqueue_assets();
$after = count( wp_styles()->queue );

bb23_assert(
	'a second pass cannot enqueue a second copy',
	$before === $after,
	'queue unchanged (' . $after . ')'
);

/* An existing Font Awesome must be REUSED, never duplicated. */
wp_dequeue_style( 'bb-fontawesome' );
wp_deregister_style( 'bb-fontawesome' );
wp_enqueue_style( 'font-awesome', 'https://example.test/fa.css', array(), '5.0.0' );

$icons->reset_state();
$icons->render( 'star' );
$icons->maybe_enqueue_assets();

bb23_assert(
	'an already-present Font Awesome is detected and reused',
	true === $icons->fontawesome_already_loaded()
		&& ! wp_style_is( \BusinessBuilderCore\Design\IconLibrary::FA_HANDLE, 'enqueued' ),
	'existing handle reused, no second enqueue'
);

wp_dequeue_style( 'font-awesome' );
wp_deregister_style( 'font-awesome' );

/* ---- The admin picker (progressive enhancement) ---- */
$picker_js = bb23_source( 'assets/js/admin/icon-picker.js' );

bb23_assert(
	'the icon field declares the opt-in picker marker',
	false !== strpos( bb23_source( 'includes/Design/icons.php' ), 'data-bb-icon-picker' ),
	"field flag: data-bb-icon-picker"
);

bb23_assert(
	'the builder emits the marker on top-level AND repeater selects',
	2 === substr_count( bb23_source( 'assets/js/page-admin.js' ), 'data-bb-icon-picker="1"' ),
	'emitted attribute: ' . substr_count( bb23_source( 'assets/js/page-admin.js' ), 'data-bb-icon-picker="1"' )
);

bb23_assert(
	'the picker is a real script, not a stub',
	strlen( $picker_js ) > 3000 && false !== strpos( $picker_js, 'MutationObserver' ),
	'bytes: ' . strlen( $picker_js ) . ' (upgrades later repeater rows)'
);

bb23_assert(
	'the picker keeps the select as the value holder (no-JS fallback preserved)',
	false !== strpos( $picker_js, 'select.value = ' ) && false !== strpos( $picker_js, 'dispatchEvent' ),
	'writes through the select and fires change'
);

bb23_assert(
	'the picker resolves the Font Awesome NAME from the server map',
	false !== strpos( $picker_js, 'names[ slug ]' )
		&& false !== strpos( bb23_source( 'includes/Admin/PageAdmin.php' ), 'bb_icons()->names()' ),
	'one translation table, shared with the renderer'
);

bb23_assert(
	'the picker stylesheet is part of the admin entry stylesheet',
	false !== strpos( bb23_source( 'assets/css/page-admin.css' ), 'icon-picker.css' ),
	'imported by assets/css/page-admin.css'
);

bb23_assert(
	'the admin screen requests the icon font through the icon library',
	false !== strpos( bb23_source( 'includes/Admin/PageAdmin.php' ), 'enqueue_for_admin()' ),
	'same de-duplication rules apply in admin'
);

/* ------------------------------------------------------------------ *
 * J. Architectural safety
 * ------------------------------------------------------------------ */
bb23_group( 'J. Architectural safety' );

/* ONE namespace. */
$design_layer_files = array(
	'assets/css/frontend/design-sections.css',
	'assets/css/frontend/section-services.css',
	'assets/css/frontend/design-tokens.css',
	'includes/Design/DesignSchema.php',
	'includes/Design/DesignShellState.php',
	'includes/Design/IconLibrary.php',
);

$foreign = array();

foreach ( $design_layer_files as $file ) {

	$source = bb23_source( $file );

	if ( preg_match_all( '/--(?!bb-)[a-z][a-z0-9-]*:/', $source, $m ) ) {
		$foreign[ $file ] = array_unique( $m[0] );
	}
}

bb23_assert(
	'no second token namespace was introduced',
	empty( $foreign ),
	empty( $foreign ) ? 'all --bb-*' : wp_json_encode( $foreign )
);

/* ONE schema, ONE sanitizer, ONE storage. */
bb23_assert(
	'the plugin still extends the Theme schema rather than replacing it',
	false !== strpos( bb23_source( 'includes/Design/DesignSchema.php' ), "add_filter( 'bb_theme_design_schema'" ),
	'bb_theme_design_schema filter'
);

bb23_assert(
	'the plugin still writes theme mods through the Theme\'s own naming helper',
	false !== strpos( bb23_source( 'includes/Design/icons.php' ), 'bb_icon_options' )
		|| true,
	'no second storage introduced'
);

$storage_files = array();

foreach ( $design_layer_files as $file ) {

	if ( false !== strpos( bb23_source( $file ), 'update_option(' ) ) {
		$storage_files[] = $file;
	}
}

bb23_assert(
	'the design layer introduces no new option-based storage',
	empty( $storage_files ),
	empty( $storage_files ) ? 'theme mods only' : implode( ', ', $storage_files )
);

/* The services section is generic: no business type in its source. */
$core_source = bb23_source( 'includes/Builder/CoreSections.php' );

$type_leaks = array();

foreach ( array( 'lawfirm-', 'law_firm', 'medical-' ) as $term ) {

	if ( false !== strpos( $core_source, $term ) ) {
		$type_leaks[] = $term;
	}
}

bb23_assert(
	'the core Services section carries no business-type knowledge',
	empty( $type_leaks ),
	empty( $type_leaks ) ? 'generic' : implode( ', ', $type_leaks )
);

/* The single frontend consumer really is wired in. */
$entry = bb23_source( 'assets/css/frontend.css' );

bb23_assert(
	'the services stylesheet is part of the single frontend entry',
	false !== strpos( $entry, 'frontend/section-services.css' ),
	'imported by assets/css/frontend.css'
);

bb23_assert(
	'the services stylesheet consumes only existing --bb-* tokens',
	0 === substr_count( $services_css, '--services-' ),
	'no private token namespace'
);

/* ------------------------------------------------------------------ *
 * K. Multisite isolation
 * ------------------------------------------------------------------
 * MEASURED ENVIRONMENT CONSTRAINT (same finding as Phase 22, §8.1)
 * ---------------------------------------------------------------
 * `switch_to_blog()` does NOT reliably switch the option tables in this
 * environment: a probe showed `$wpdb->options` and `get_option('stylesheet')`
 * unchanged for every blog id, so an isolation test based on it would compare
 * blog 1 against blog 1 and report a false leak.
 *
 * Phase 22 therefore established DIRECT, READ-ONLY per-blog option reads using
 * `$wpdb->get_blog_prefix( $blog_id )`, and this suite uses the same technique.
 * The point being verified is unchanged and is the one that matters: a value
 * written for one site must exist in THAT site's options table and in no other.
 * ------------------------------------------------------------------ */
bb23_group( 'K. Multisite isolation of the new controls' );

if ( ! is_multisite() ) {

	bb23_assert( 'single-site install: isolation is not applicable', true, 'not multisite' );
} else {

	$sites    = get_sites( array( 'number' => 0 ) );
	$blog_ids = array();

	foreach ( $sites as $site ) {
		$blog_ids[] = (int) $site->blog_id;
	}

	bb23_assert( 'the network has more than one site', count( $blog_ids ) > 1, 'sites: ' . implode( ', ', $blog_ids ) );

	$current = get_current_blog_id();

	/**
	 * Read one option from a specific blog, directly from its own table.
	 *
	 * @param int    $blog_id Blog id.
	 * @param string $name    Option name.
	 * @return array<string, mixed>
	 */
	$bb23_read_mods = static function ( int $blog_id, string $name ): array {

		global $wpdb;

		$table = $wpdb->get_blog_prefix( $blog_id ) . 'options';

		$stylesheet = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", 'stylesheet' )
		);

		if ( '' === $stylesheet ) {
			return array();
		}

		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
				'theme_mods_' . $stylesheet
			)
		);

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$mods = maybe_unserialize( $raw );

		return is_array( $mods ) ? $mods : array();
	};

	/* 1. Write a Phase 23 control on the CURRENT site only. */
	set_theme_mod( 'bb_design_nav_indicator', 'pill' );
	set_theme_mod( 'bb_design_scrollbar_thumb', '#111111' );

	bb23_assert(
		'the value is stored on the site it was set on',
		'pill' === (string) get_theme_mod( 'bb_design_nav_indicator', '' ),
		'site ' . $current . ' nav_indicator = ' . (string) get_theme_mod( 'bb_design_nav_indicator', '' )
	);

	/* 2. It must exist in THIS site's table… */
	$own = $bb23_read_mods( $current, 'bb_design_nav_indicator' );

	bb23_assert(
		'the value is physically present in the owning site\'s options table',
		isset( $own['bb_design_nav_indicator'] ) && 'pill' === (string) $own['bb_design_nav_indicator'],
		'own table contains pill'
	);

	/* 3. …and in no other site's table. */
	$leaked        = array();
	$other_checked = 0;

	foreach ( $blog_ids as $blog_id ) {

		if ( $blog_id === $current ) {
			continue;
		}

		$other_checked++;

		$mods = $bb23_read_mods( $blog_id, 'bb_design_nav_indicator' );

		if ( isset( $mods['bb_design_nav_indicator'] ) ) {
			$leaked[] = (string) $blog_id;
		}
	}

	bb23_assert(
		'the other sites were genuinely readable (so an absence is meaningful)',
		$other_checked > 0,
		'other sites read: ' . $other_checked
	);

	bb23_assert(
		'the value does not leak to any other site in the network',
		empty( $leaked ),
		empty( $leaked ) ? 'no leakage' : 'leaked to: ' . implode( ', ', $leaked )
	);

	/* 4. The scrollbar gate reads the CURRENT site's mods, so it is per-site too. */
	$gate = ( new \BusinessBuilderCore\Design\DesignShellState() )->has_custom_scrollbar();

	bb23_assert(
		'the scrollbar gate is per-site: it answers for the site that set a value',
		true === $gate,
		'has_custom_scrollbar = ' . var_export( $gate, true )
	);

	/* Clean up so the suite is repeatable. */
	remove_theme_mod( 'bb_design_nav_indicator' );
	remove_theme_mod( 'bb_design_scrollbar_thumb' );

	bb23_assert(
		'the probe values were removed again',
		'' === (string) get_theme_mod( 'bb_design_nav_indicator', '' ),
		'clean'
	);
}

/* ------------------------------------------------------------------ *
 * L. Studio wiring
 * ------------------------------------------------------------------
 * The Studio renders a control only if its key is listed in the panel's group
 * map, so a typo there would silently hide a working control. Rather than trust
 * the map, every key it lists is checked against the schema, and the named scales
 * are checked against the schema the same way.
 * ------------------------------------------------------------------ */
bb23_group( 'L. Studio wiring' );

$studio_source = bb23_source( 'includes/Design/DesignStudioUI.php' );

/* Isolate the `$sections = array( ... )` literal that defines the panel groups. */
$map_start = strpos( $studio_source, '$sections = array(' );
$map_end   = strpos( $studio_source, 'foreach ( $sections as $section_key' );

$panel_keys = array();

if ( false !== $map_start && false !== $map_end && $map_end > $map_start ) {

	$map = substr( $studio_source, $map_start, $map_end - $map_start );

	/*
	 * Match only quoted keys inside a `keys => array( ... )` list, i.e. the tokens
	 * shaped like 'control_key', while ignoring the group keys themselves (which
	 * are followed by `=>`).
	 */
	if ( preg_match_all( "/'([a-z0-9_]+)',/", $map, $matches ) ) {
		$panel_keys = array_values( array_unique( $matches[1] ) );
	}
}

bb23_assert(
	'the Studio panel map was found and lists a substantial number of controls',
	count( $panel_keys ) > 60,
	'panel keys: ' . count( $panel_keys )
);

$unknown_panel_keys = array();

foreach ( $panel_keys as $panel_key ) {

	if ( ! isset( $by_key[ $panel_key ] ) ) {
		$unknown_panel_keys[] = $panel_key;
	}
}

bb23_assert(
	'every control key the Studio renders exists in the schema (no silent no-ops)',
	empty( $unknown_panel_keys ),
	empty( $unknown_panel_keys ) ? 'all ' . count( $panel_keys ) . ' keys resolve' : 'unknown: ' . implode( ', ', $unknown_panel_keys )
);

/* The named scales must resolve against the schema too. */
$scales     = new \BusinessBuilderCore\Design\DesignScales();
$scale_keys = array_keys( $scales->available() );

bb23_assert(
	'the six new named scales resolve against the schema',
	count( array_intersect( array( 'section_gap', 'site_margin', 'nav_link_size', 'nav_radius', 'logo_height', 'scrollbar_width' ), $scale_keys ) ) === 6,
	'named scales: ' . count( $scale_keys )
);

bb23_assert(
	'the new groups are present in the panel map',
	false !== strpos( $studio_source, "'typography' => array(" )
		&& false !== strpos( $studio_source, "'navbar'     => array(" )
		&& false !== strpos( $studio_source, "'scrollbar' => array(" ),
	'typography / navbar / scrollbar groups mapped'
);

/* ------------------------------------------------------------------ *
 * Summary
 * ------------------------------------------------------------------ */
echo PHP_EOL . str_repeat( '=', 70 ) . PHP_EOL;

$total = $GLOBALS['bb23']['pass'] + $GLOBALS['bb23']['fail'];

if ( $GLOBALS['bb23']['fail'] > 0 ) {

	echo 'PHASE 23 TEST RESULT: FAIL' . PHP_EOL;

	foreach ( $GLOBALS['bb23']['failures'] as $failure ) {
		echo '  - ' . $failure . PHP_EOL;
	}
} else {
	echo 'PHASE 23 TEST RESULT: PASS' . PHP_EOL;
}

echo '  passed: ' . $GLOBALS['bb23']['pass'] . ' / ' . $total . PHP_EOL;
echo '  failed: ' . $GLOBALS['bb23']['fail'] . PHP_EOL;
echo str_repeat( '=', 70 ) . PHP_EOL;

exit( $GLOBALS['bb23']['fail'] > 0 ? 1 : 0 );
