<?php
/**
 * PHASE 23 — READ-ONLY CONTROL AUDIT (produced BEFORE any edit).
 * =============================================================
 * This script answers, with measured values rather than assumptions:
 *
 *   1. Which design controls exist (Theme schema + plugin schema + section schema)?
 *   2. What token does each one write, where is it stored, and WHO consumes it?
 *   3. Which controls are DUPLICATES (two controls writing one token)?
 *   4. Which tokens are ORPHANED (no consumer anywhere == "UI only")?
 *
 * It changes nothing. Run:
 *
 *   php tests/_phase23-audit.php
 *
 * @package BusinessBuilderCore
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

$plugin = dirname( __DIR__ );
$theme  = dirname( $plugin, 2 ) . '/themes/business-builder';

define( 'BB23_PLUGIN', $plugin );
define( 'BB23_THEME', $theme );

/*
 * The Theme's own schema lives in the THEME, and the Theme is not active on every site
 * (site 1 runs Astra). Loading its schema file directly is what makes this audit complete
 * rather than only seeing whichever schema the active theme happens to expose.
 */
if ( ! function_exists( 'bb_theme_design_schema' ) ) {

	$theme_schema_file = BB23_THEME . '/inc/design-schema.php';

	if ( is_readable( $theme_schema_file ) ) {
		require_once $theme_schema_file;
	} else {
		$GLOBALS['bb23_theme_schema_missing'] = true;
	}
}

$roots = array( BB23_PLUGIN . '/assets', BB23_PLUGIN . '/includes', BB23_PLUGIN . '/modules', BB23_PLUGIN . '/packs', BB23_THEME );

$files = array();

foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $it as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		$ext = strtolower( $file->getExtension() );

		if ( ! in_array( $ext, array( 'css', 'js', 'php' ), true ) ) {
			continue;
		}

		$files[] = $file->getPathname();
	}
}

echo 'scanned files: ' . count( $files ) . PHP_EOL;

/**
 * Measure how a token is used, separating two very different things:
 *
 *   FE  — a real FRONTEND consumption: a `var(--token)` in a stylesheet, or the
 *         token named by frontend JavaScript. This is what "reaches the frontend"
 *         means.
 *   PHP — author-side reference: a schema declaration, a design preset offering a
 *         value, or a resolver reading it server-side. Useful, but NOT proof that
 *         the customer's choice is visible.
 *
 * A token's own CSS declaration (`--token:`) is subtracted: declaring a default is
 * not consuming it.
 *
 * @param string   $token Token name.
 * @param string[] $files Files to scan.
 * @return array{fe:int,php:int,uses:int,sources:string[]}
 */
function bb23_audit_uses( string $token, array $files ): array {

	$fe      = 0;
	$php     = 0;
	$sources = array();

	/* Read each file once: the audit walks the same tree per token. */
	static $cache = array();

	foreach ( $files as $file ) {

		if ( ! isset( $cache[ $file ] ) ) {
			$cache[ $file ] = (string) file_get_contents( $file );
		}

		$contents = $cache[ $file ];
		$ext      = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$count    = 0;

		/* CSS consumption: var( --token ... ) */
		$count += (int) preg_match_all( '/' . preg_quote( $token, '/' ) . '\s*[,)]/', $contents );

		/* JS / PHP: the token named in a quoted string. */
		$count += (int) preg_match_all( '/[\'"]' . preg_quote( $token, '/' ) . '[\'"]/', $contents );

		/* The token's own declaration (that is not consumption). */
		$count -= (int) preg_match_all( '/' . preg_quote( $token, '/' ) . '\s*:/', $contents );

		if ( $count <= 0 ) {
			continue;
		}

		if ( 'php' === $ext || 'template-parts' === basename( dirname( $file ) ) ) {
			$php += $count;
		} else {
			$fe += $count;
		}

		$sources[] = str_replace( array( BB23_PLUGIN . '/', BB23_THEME . '/' ), array( '', 'theme/' ), $file ) . ' (' . $count . ')';
	}

	return array(
		'fe'      => $fe,
		'php'     => $php,
		'uses'    => $fe + $php,
		'sources' => $sources,
	);
}

/* ------------------------------------------------------------------ *
 * A. The global control inventory.
 * ------------------------------------------------------------------ */
$schema   = function_exists( 'bb_theme_design_schema' ) ? bb_theme_design_schema() : array();
$instance = new \BusinessBuilderCore\Design\DesignSchema();

$plugin_keys = array();

foreach ( $instance->controls() as $control ) {
	$plugin_keys[ (string) $control['key'] ] = true;
}

$unique_tokens = array();
$rows          = array();

foreach ( $schema as $control ) {

	if ( ! is_array( $control ) ) {
		continue;
	}

	$key   = (string) ( $control['key'] ?? '' );
	$token = (string) ( $control['token'] ?? '' );

	if ( '' === $key || '' === $token ) {
		continue;
	}

	$audit = bb23_audit_uses( $token, $files );

	$unique_tokens[ $token ][] = $key;

	$rows[] = array(
		'key'     => $key,
		'token'   => $token,
		'type'    => (string) ( $control['type'] ?? '' ),
		'group'   => (string) ( $control['group'] ?? '' ),
		'owner'   => isset( $plugin_keys[ $key ] ) ? 'plugin' : 'theme',
		'fe'      => $audit['fe'],
		'php'     => $audit['php'],
		'sources' => implode( ', ', $audit['sources'] ),
	);
}

echo PHP_EOL . '== GLOBAL CONTROLS: ' . count( $rows ) . ' ==' . PHP_EOL;
echo str_pad( 'CONTROL', 30 ) . str_pad( 'TOKEN', 32 ) . str_pad( 'GROUP', 12 ) . str_pad( 'OWNER', 8 ) . str_pad( 'FE', 5 ) . str_pad( 'PHP', 5 ) . 'STATUS' . PHP_EOL;

foreach ( $rows as $row ) {
	printf(
		"%-30s %-32s %-12s %-8s %-5d %-5d %s\n",
		$row['key'],
		$row['token'],
		$row['group'],
		$row['owner'],
		$row['fe'],
		$row['php'],
		0 === $row['fe']
			? ( $row['php'] > 0 ? 'NO FRONTEND CONSUMER (author-side only)' : 'INERT (nothing anywhere)' )
			: 'ok'
	);
}

echo PHP_EOL . '== DUPLICATE TOKENS (two controls -> one token) ==' . PHP_EOL;

$dupes = 0;

foreach ( $unique_tokens as $token => $keys ) {
	if ( count( $keys ) > 1 ) {
		$dupes++;
		echo "  {$token}  <-  " . implode( ', ', $keys ) . PHP_EOL;
	}
}

echo $dupes ? '' : '  none' . PHP_EOL;

echo PHP_EOL . '== ORPHANED CONTROLS (no frontend consumer) ==' . PHP_EOL;

$orphans = 0;

foreach ( $rows as $row ) {
	if ( 0 === $row['fe'] ) {
		$orphans++;
		printf(
			"  %-30s %-32s group=%-11s owner=%-7s php-refs=%d\n",
			$row['key'],
			$row['token'],
			$row['group'],
			$row['owner'],
			$row['php']
		);
	}
}

echo $orphans ? '' : '  none' . PHP_EOL;

/*
 * ------------------------------------------------------------------
 * INDIRECT CONSUMERS
 * ------------------------------------------------------------------
 * A `select` control stores a KEYWORD, and a keyword cannot be branched on from
 * CSS. Those controls are therefore consumed in two steps:
 *
 *   token  ->  PHP state emitter  ->  class / data attribute  ->  CSS
 *
 * The literal-token sweep above cannot see that second step, so it would report
 * a perfectly working control as an orphan. Measuring the ATTRIBUTE each token
 * drives closes the loop and keeps the orphan list honest.
 * ------------------------------------------------------------------
 */
echo PHP_EOL . '== INDIRECT CONSUMERS (keyword controls) ==' . PHP_EOL;

/**
 * token => [ description, CSS probe strings that must exist ].
 *
 * @var array<string, array{0:string,1:string[]}>
 */
$indirect = array(
	'--bb-reveal-kind'          => array( 'data-bb-reveal', array( '[data-bb-reveal=' ) ),
	'--bb-section-reveal'       => array( 'data-bb-reveal', array( '[data-bb-reveal=' ) ),
	'--bb-hover-effect'         => array( 'data-bb-hover', array( '[data-bb-hover=' ) ),
	'--bb-section-hover'        => array( 'data-bb-hover', array( '[data-bb-hover=' ) ),
	'--bb-layout-mode'          => array( 'body class .has-bb-layout-boxed', array( '.has-bb-layout-boxed' ) ),
	'--bb-nav-position'         => array( 'body class .has-bb-nav-start/end', array( '.has-bb-nav-start' ) ),
	'--bb-nav-indicator'        => array( 'body class .has-bb-nav-underline/pill/dot', array( '.has-bb-nav-pill' ) ),
	'--bb-motion-mode'          => array( 'body class .has-bb-motion-off', array( '.has-bb-motion-off' ) ),
	'--bb-bg-overlay-opacity'   => array( 'body class .has-bb-overlay', array( '.has-bb-overlay' ) ),
);

$state_source = (string) file_get_contents( BB23_PLUGIN . '/includes/Design/DesignShellState.php' )
	. (string) file_get_contents( BB23_PLUGIN . '/includes/Builder/section-presentation.php' );

foreach ( $indirect as $token => $probe ) {

	/* 1. Is the token actually read by a state emitter? */
	$read = false !== strpos( $state_source, $token );

	/* 2. Does the attribute/class it drives have a stylesheet consumer? */
	$consumed = true;

	foreach ( $probe[1] as $needle ) {

		$found = false;

		foreach ( $files as $file ) {

			if ( 'css' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			if ( false !== strpos( (string) file_get_contents( $file ), $needle ) ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			$consumed = false;
		}
	}

	printf(
		"%-30s -> %-40s read=%-5s css=%-5s %s\n",
		$token,
		$probe[0],
		$read ? 'yes' : 'no',
		$consumed ? 'yes' : 'no',
		( $read && $consumed ) ? 'CONSUMED (indirect)' : '*** NOT CONSUMED ***'
	);
}

echo PHP_EOL . '== FRONTEND CONSUMER MAP ==' . PHP_EOL;

foreach ( $rows as $row ) {
	if ( 0 === $row['fe'] ) {
		continue;
	}

	printf( "  %-30s -> %s\n", $row['key'], $row['sources'] );
}

/* ------------------------------------------------------------------ *
 * B. Section-scoped controls.
 * ------------------------------------------------------------------ */
$section_schema = new \BusinessBuilderCore\Design\SectionStyleSchema();

echo PHP_EOL . '== SECTION-LEVEL CONTROLS ==' . PHP_EOL;

$section_orphans = 0;

foreach ( $section_schema->groups() as $group_key => $group ) {

	foreach ( (array) ( $group['controls'] ?? array() ) as $control ) {

		$token = (string) ( $control['token'] ?? '' );
		$audit = bb23_audit_uses( $token, $files );

		if ( 0 === $audit['fe'] ) {
			$section_orphans++;
		}

		printf(
			"%-13s %-34s %-7s FE=%-4d PHP=%-4d %s\n",
			$group_key,
			$token,
			(string) ( $control['type'] ?? '' ),
			$audit['fe'],
			$audit['php'],
			$audit['fe'] > 0 ? implode( ', ', $audit['sources'] ) : '*** NO FRONTEND CONSUMER ***'
		);
	}
}

echo 'section orphans: ' . $section_orphans . PHP_EOL;

/*
 * A section control builds its token name from TOKEN_PREFIX + a suffix, so the literal
 * token never appears in the source. Expanding it here is what makes a GLOBAL/section
 * token collision detectable instead of invisible.
 */
echo PHP_EOL . '== GLOBAL vs SECTION TOKEN COLLISIONS ==' . PHP_EOL;

$global_tokens = array();

foreach ( $rows as $row ) {
	$global_tokens[ $row['token'] ] = $row['key'];
}

$collisions = 0;

foreach ( $section_schema->groups() as $group_key => $group ) {

	foreach ( (array) ( $group['controls'] ?? array() ) as $control ) {

		$token = (string) ( $control['token'] ?? '' );

		if ( isset( $global_tokens[ $token ] ) ) {
			$collisions++;
			printf(
				"  %s  written by BOTH global '%s' and section '%s' (%s)\n",
				$token,
				$global_tokens[ $token ],
				(string) $control['key'],
				$group_key
			);
		}
	}
}

echo $collisions ? '' : '  none' . PHP_EOL;

echo 'resolved section tokens: ' . count( $section_schema->groups() ) . ' groups' . PHP_EOL;


/* ------------------------------------------------------------------ *
 * C. Registered sections, variants, capabilities.
 * ------------------------------------------------------------------ */
echo PHP_EOL . '== REGISTERED SECTIONS ==' . PHP_EOL;

$registry = new \BusinessBuilderCore\Builder\SectionRegistry();

$core = new \BusinessBuilderCore\Builder\CoreSections( $registry );
$core->register();

foreach ( $registry->get_all() as $slug => $section ) {
	printf(
		"%-18s cat=%-14s supports=%-44s settings=%-3d content=%d\n",
		$slug,
		(string) ( $section['category'] ?? '' ),
		implode( '|', (array) ( $section['supports'] ?? array() ) ),
		count( (array) ( $section['settings'] ?? array() ) ),
		count( (array) ( $section['content'] ?? array() ) )
	);
}

echo PHP_EOL . '== SECTION VARIANTS (core) ==' . PHP_EOL;

$variants = bb_section_variants();

foreach ( $variants->all() as $type => $set ) {
	echo "  {$type}: " . implode( ', ', array_keys( (array) $set ) ) . PHP_EOL;
}

echo PHP_EOL . '== THEME SHELL VARIANTS ==' . PHP_EOL;

foreach ( array( 'header', 'navigation', 'footer' ) as $part ) {
	$fn = 'bb_theme_shell_' . $part . '_variants';

	if ( function_exists( $fn ) ) {
		echo "  {$part}: " . implode( ', ', array_keys( (array) $fn() ) ) . PHP_EOL;
	} else {
		echo "  {$part}: (no resolver)" . PHP_EOL;
	}
}

echo PHP_EOL . '== ENVIRONMENT ==' . PHP_EOL;
printf(
	'  theme: %s | site: %d | business type: %s | builder theme present: %s | theme schema file: %s' . PHP_EOL,
	get_stylesheet(),
	get_current_blog_id(),
	function_exists( 'bb_get_business_type' ) ? (string) bb_get_business_type() : 'n/a',
	function_exists( 'bb_theme_design_schema' ) ? 'yes' : 'NO',
	empty( $GLOBALS['bb23_theme_schema_missing'] ) ? 'loaded' : 'MISSING'
);

if ( is_multisite() ) {

	echo PHP_EOL . '== PER-SITE ISOLATION ==' . PHP_EOL;

	foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {

		switch_to_blog( (int) $site->blog_id );

		$mods = get_theme_mods();
		$design_mods = array();

		foreach ( (array) $mods as $name => $value ) {
			if ( 0 === strpos( (string) $name, 'bb_design' ) ) {
				$design_mods[ $name ] = is_array( $value ) ? '{array}' : $value;
			}
		}

		printf(
			"  site %d: theme=%-16s type=%-12s preset=%-18s design mods=%d\n",
			(int) $site->blog_id,
			get_stylesheet(),
			(string) get_option( 'bb_business_type', '(none)' ),
			(string) ( $mods['bb_theme_preset'] ?? '(unset)' ),
			count( $design_mods )
		);

		restore_current_blog();
	}
}
