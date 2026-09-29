<?php
/**
 * PHASE 24 — READ-ONLY LAYOUT / VISUAL AUDIT (produced BEFORE any edit).
 * =====================================================================
 * Phase 23's audit answered "which control, which token, who consumes it".
 * Phase 24 needs FOUR measurements that audit could not produce, because the
 * Phase 24 failures are not missing tokens — they are tokens that a DIFFERENT
 * layer overwrites, and pixels that no control owns at all:
 *
 *   B. HORIZONTAL SPACE MAP (§4/§5)
 *      Every declaration anywhere in the Theme or the plugin that can add
 *      horizontal space to the page frame (`.bb-template`, `.bb-site`,
 *      `.bb-main`, `.bb-container`, `.bb-section`, `.bb-section-inner`, the
 *      header/footer inners). Each one is classified:
 *
 *        ZERO      -> literally 0, contributes nothing
 *        TOKEN     -> var(--bb-…), therefore ONE owner and Studio-reachable
 *        HARDCODE  -> a literal non-zero value owned by NO control  <- the bug
 *
 *      This is what proves, rather than assumes, where "Space around the
 *      content = 0" still leaves pixels behind.
 *
 *   D. HARDCODED VALUES (§55/§57)
 *      Colour / font-family / font-size / spacing / background / border
 *      literals found OUTSIDE the token-definition files. A literal in a
 *      consumer file is a value the Design cannot change.
 *
 *   E. PREVIEW vs LIVE DIVERGENCE (§58/§59)
 *      The same selector + the same property declared with DIFFERENT values in
 *      a preview stylesheet (`assets/css/page-admin/**`) and a frontend
 *      stylesheet (`assets/css/frontend/**`). These are, by definition,
 *      "preview != live" bugs.
 *
 *   F. UNWANTED BORDERS (§57)
 *      Literal borders on shared/structural selectors, which is what makes a
 *      section show a divider line that no Design asked for.
 *
 * It changes nothing. Run:
 *
 *   php tests/_phase24-audit.php
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

define( 'BB24_PLUGIN', $plugin );
define( 'BB24_THEME', $theme );

/* ------------------------------------------------------------------ *
 * 0. Helpers — a small, dependency-free CSS scanner.
 * ------------------------------------------------------------------ */

/**
 * Split a stylesheet into rules, keeping the enclosing @-rule context.
 *
 * Hand-rolled rather than regex-based on purpose: `@media (max-width: 767px)
 * { .a { … } }` must yield the rule `.a` WITH its media context, and a flat
 * `([^{}]+)\{([^{}]*)\}` regex silently drops that context. Since the whole
 * point of this audit is to prove which breakpoint a value comes from, the
 * context is not optional.
 *
 * Comments are stripped first so a commented-out declaration is never counted.
 *
 * @param string $css Raw stylesheet.
 * @return array<int,array{selector:string,body:string,context:string,line:int}>
 */
function bb24_parse_css( string $css ): array {

	/*
	 * Comments are replaced by an EQUAL NUMBER OF NEWLINES rather than simply
	 * deleted, so every reported line number stays the real one in the file.
	 * Simply stripping them silently shifted every line number after a
	 * multi-line comment, which would have made the audit's evidence
	 * un-followable.
	 */
	$css = (string) preg_replace_callback(
		'#/\*.*?\*/#s',
		static function ( array $m ): string {
			return str_repeat( "\n", substr_count( $m[0], "\n" ) );
		},
		$css
	);

	$len   = strlen( $css );
	$stack = array( array( 'sel' => '', 'line' => 1 ) );
	$buf   = '';
	$cur   = 1;
	$rules = array();

	for ( $i = 0; $i < $len; $i++ ) {

		$c = $css[ $i ];

		if ( "\n" === $c ) {
			$cur++;
			continue;
		}

		if ( '{' === $c ) {
			$stack[] = array( 'sel' => trim( $buf ), 'line' => $cur );
			$buf     = '';
			continue;
		}

		if ( '}' === $c ) {

			$frame = array_pop( $stack );

			if ( ! $frame ) {
				$buf = '';
				continue;
			}

			/* An @-rule wrapper holds rules, not declarations: discard it. */
			if ( '' !== $frame['sel'] && 0 !== strpos( $frame['sel'], '@' ) ) {

				$context = array();

				foreach ( $stack as $parent ) {
					if ( '' !== $parent['sel'] && 0 === strpos( $parent['sel'], '@' ) ) {
						$context[] = $parent['sel'];
					}
				}

				$rules[] = array(
					'selector' => $frame['sel'],
					'body'     => $buf,
					'context'  => implode( ' ', $context ),
					'line'     => $frame['line'],
				);
			}

			$buf = '';
			continue;
		}

		$buf .= $c;
	}

	return $rules;
}

/**
 * Turn a rule body into `property => value` pairs.
 *
 * @param string $body Declaration block.
 * @return array<string,string>
 */
function bb24_declarations( string $body ): array {

	$out = array();

	foreach ( explode( ';', $body ) as $decl ) {

		$decl = trim( $decl );

		if ( '' === $decl || false === strpos( $decl, ':' ) ) {
			continue;
		}

		list( $prop, $value ) = explode( ':', $decl, 2 );

		$prop  = strtolower( trim( $prop ) );
		$value = trim( $value );

		if ( '' === $prop || '' === $value ) {
			continue;
		}

		/* Keep the LAST declaration: that is the one the browser applies. */
		$out[ $prop ] = $value;
	}

	return $out;
}

/**
 * Collect every scanneable file under a root.
 *
 * @param string   $root Directory.
 * @param string[] $exts Extensions to include.
 * @return string[]
 */
function bb24_files( string $root, array $exts = array( 'css' ) ): array {

	$files = array();

	if ( ! is_dir( $root ) ) {
		return $files;
	}

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $it as $file ) {

		if ( $file->isFile() && in_array( strtolower( $file->getExtension() ), $exts, true ) ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}

/**
 * Classify a CSS value as tokenised, zero, or a hardcoded literal.
 *
 * @param string $value CSS value.
 * @return string ZERO | TOKEN | KEYWORD | HARDCODE
 */
function bb24_classify( string $value ): string {

	$v = strtolower( trim( $value ) );

	if ( preg_match( '/^(0|0px|0rem|0em|0%|0vh|0vw)$/', $v ) ) {
		return 'ZERO';
	}

	if ( false !== strpos( $v, 'var(--bb-' ) ) {
		return 'TOKEN';
	}

	if ( in_array( $v, array( 'auto', 'none', 'inherit', 'unset', 'initial', 'revert', 'transparent', 'normal', 'start', 'center' ), true ) ) {
		return 'KEYWORD';
	}

	return 'HARDCODE';
}

/**
 * Make a path relative to the plugin, the theme, or the wp-content root.
 *
 * @param string $path Absolute path.
 * @return string
 */
function bb24_rel( string $path ): string {

	$path = str_replace( '\\', '/', $path );

	foreach ( array( 'PLUGIN' => BB24_PLUGIN, 'THEME' => BB24_THEME ) as $label => $root ) {

		$root = str_replace( '\\', '/', $root );

		if ( 0 === strpos( $path, $root ) ) {
			return $label . substr( $path, strlen( $root ) );
		}
	}

	return $path;
}

/**
 * Every stylesheet that participates in the rendered page, tagged by LAYER.
 *
 * The layer matters: a rule in the THEME and a rule in the plugin's FRONTEND
 * layer compete by load order and specificity, while a rule in the PREVIEW
 * layer is a different document entirely (the Studio iframe) and therefore a
 * parity risk rather than a conflict.
 *
 * @return array<string,string> path => LAYER
 */
function bb24_layers(): array {

	$map = array();

	foreach ( bb24_files( BB24_THEME . '/assets/css' ) as $f ) {
		$map[ $f ] = 'THEME';
	}

	$map[ BB24_THEME . '/style.css' ] = 'THEME';

	foreach ( bb24_files( BB24_PLUGIN . '/assets/css/frontend' ) as $f ) {
		$map[ $f ] = 'FRONTEND';
	}

	$map[ BB24_PLUGIN . '/assets/css/frontend.css' ] = 'FRONTEND';

	foreach ( bb24_files( BB24_PLUGIN . '/assets/css/page-admin' ) as $f ) {
		$map[ $f ] = 'PREVIEW';
	}

	$map[ BB24_PLUGIN . '/assets/css/page-admin.css' ] = 'PREVIEW';

	foreach ( bb24_files( BB24_PLUGIN . '/packs', array( 'css' ) ) as $f ) {
		$map[ $f ] = 'PACK';
	}

	return array_filter( $map, static function ( $layer, $path ) { return is_readable( $path ); }, ARRAY_FILTER_USE_BOTH );
}

/* ------------------------------------------------------------------ *
 * Build the parsed corpus ONCE: every pass reads the same rules.
 * ------------------------------------------------------------------ */
$layers = bb24_layers();
$corpus = array();

foreach ( $layers as $path => $layer ) {

	$corpus[] = array(
		'path'  => $path,
		'rel'   => bb24_rel( $path ),
		'layer' => $layer,
		'rules' => bb24_parse_css( (string) file_get_contents( $path ) ),
	);
}

$total_rules = 0;

foreach ( $corpus as $doc ) {
	$total_rules += count( $doc['rules'] );
}

echo 'PHASE 24 AUDIT — read-only' . PHP_EOL;
echo 'stylesheets: ' . count( $corpus ) . ' | rules parsed: ' . $total_rules . PHP_EOL;

/* ------------------------------------------------------------------ *
 * B. HORIZONTAL SPACE MAP (§4/§5)
 * ------------------------------------------------------------------ */

/**
 * Selectors that form the horizontal page frame. Ordered OUTERMOST first,
 * because that is the order a browser resolves them in, and the audit output
 * must be readable in that order.
 */
$frame = array(
	'html',
	'body',
	'.bb-theme',
	'.bb-site',
	'.bb-main',
	'.bb-template',
	'.bb-container',
	'.bb-section',
	'.bb-section-inner',
	'.bb-site-header',
	'.bb-site-header-inner',
	'.bb-site-footer',
	'.bb-site-footer-inner',
);

$space_props = array(
	'padding',
	'padding-inline',
	'padding-inline-start',
	'padding-inline-end',
	'padding-left',
	'padding-right',
	'margin',
	'margin-inline',
	'margin-inline-start',
	'margin-inline-end',
	'margin-left',
	'margin-right',
	'max-width',
	'max-inline-size',
	'min-width',
	'min-inline-size',
	'width',
	'inline-size',
	'gap',
	'column-gap',
);

/** Does a rule selector match one of the frame selectors? */
$matches_frame = static function ( string $selector ) use ( $frame ): ?string {

	foreach ( $frame as $target ) {

		/* Match the target as a whole word inside the selector list. */
		$pattern = '/(^|[\s,>+~])' . preg_quote( $target, '/' ) . '($|[\s,:.>+~\[])/';

		if ( preg_match( $pattern, $selector ) ) {
			return $target;
		}
	}

	return null;
};

echo PHP_EOL . '== B. HORIZONTAL SPACE MAP (§4/§5) ==' . PHP_EOL;
echo 'Every rule that can add horizontal space to the page frame.' . PHP_EOL;

$findings = array();
$hardcodes = array();

foreach ( $corpus as $doc ) {

	foreach ( $doc['rules'] as $rule ) {

		$target = $matches_frame( $rule['selector'] );

		if ( null === $target ) {
			continue;
		}

		$decls = bb24_declarations( $rule['body'] );

		foreach ( $decls as $prop => $value ) {

			if ( ! in_array( $prop, $space_props, true ) ) {
				continue;
			}

			$class = bb24_classify( $value );

			$findings[] = array(
				'layer'  => $doc['layer'],
				'target' => $target,
				'file'   => $doc['rel'],
				'line'   => (int) $rule['line'],
				'ctx'    => '' !== $rule['context'] ? $rule['context'] : '(none)',
				'prop'   => $prop,
				'value'  => $value,
				'class'  => $class,
			);

			/* A literal non-zero length is a value NO control can reach. */
			if ( 'HARDCODE' === $class && false === strpos( $value, 'var(' ) ) {
				$hardcodes[] = $findings[ count( $findings ) - 1 ];
			}
		}
	}
}

usort(
	$findings,
	static function ( $a, $b ) {
		return strcmp( $a['target'] . $a['file'] . $a['line'], $b['target'] . $b['file'] . $b['line'] );
	}
);

foreach ( $findings as $f ) {

	printf(
		"  %-9s %-22s %-6s %-42s %-18s %-22s %s\n",
		$f['class'],
		$f['target'],
		'' !== $f['ctx'] ? 'media' : '-',
		$f['file'] . ':' . $f['line'],
		$f['prop'],
		$f['value'],
		''
	);
}

printf(
	PHP_EOL . '  frame declarations: %d | TOKEN: %d | ZERO: %d | KEYWORD: %d | HARDCODE: %d' . PHP_EOL,
	count( $findings ),
	count( array_filter( $findings, static function ( $f ) { return 'TOKEN' === $f['class']; } ) ),
	count( array_filter( $findings, static function ( $f ) { return 'ZERO' === $f['class']; } ) ),
	count( array_filter( $findings, static function ( $f ) { return 'KEYWORD' === $f['class']; } ) ),
	count( array_filter( $findings, static function ( $f ) { return 'HARDCODE' === $f['class']; } ) )
);

/* Only HORIZONTAL hardcodes are the §5 violation; vertical rhythm is legitimate. */
$horizontal_hardcodes = array_values(
	array_filter(
		$hardcodes,
		static function ( $f ) {
			$p = $f['prop'];

			$is_vertical = in_array( $p, array( 'min-inline-size' ), true );

			return ! $is_vertical;
		}
	)
);

echo PHP_EOL . '== B2. HORIZONTAL HARDCODES — the pixels NO control owns (§5) ==' . PHP_EOL;

if ( empty( $horizontal_hardcodes ) ) {
	echo '  none' . PHP_EOL;
}

foreach ( $horizontal_hardcodes as $f ) {
	printf(
		"  %-9s %-22s %-42s %-18s %s\n",
		$f['layer'],
		$f['target'],
		$f['file'] . ':' . $f['line'],
		$f['prop'],
		$f['value']
	);
}

echo '  total: ' . count( $horizontal_hardcodes ) . PHP_EOL;

/* ------------------------------------------------------------------ *
 * B3. TOKEN-VS-LAYER COLLISION (§4 root cause probe)
 * ------------------------------------------------------------------ */

/*
 * A token can be correct and still not reach the page: if a DESCENDANT
 * selector re-declares it, the descendant wins no matter what `:root` says.
 * This pass reports every token that is re-declared on a frame selector, with
 * the layer and breakpoint, so "the Studio value is ignored" becomes provable.
 */
echo PHP_EOL . '== B3. TOKEN REDECLARED ON A FRAME SELECTOR (§4) ==' . PHP_EOL;

$redeclared = array();

foreach ( $corpus as $doc ) {

	foreach ( $doc['rules'] as $rule ) {

		$target = $matches_frame( $rule['selector'] );

		if ( null === $target ) {
			continue;
		}

		foreach ( bb24_declarations( $rule['body'] ) as $prop => $value ) {

			if ( 0 !== strpos( $prop, '--bb-' ) ) {
				continue;
			}

			$redeclared[] = array(
				'layer'  => $doc['layer'],
				'target' => $target,
				'file'   => $doc['rel'],
				'line'   => (int) $rule['line'],
				'ctx'    => '' !== $rule['context'] ? $rule['context'] : '(none)',
				'prop'   => $prop,
				'value'  => $value,
			);
		}
	}
}

usort(
	$redeclared,
	static function ( $a, $b ) {
		return strcmp( $a['prop'] . $a['file'] . $a['line'], $b['prop'] . $b['file'] . $b['line'] );
	}
);

foreach ( $redeclared as $r ) {
	printf(
		"  %-9s %-22s %-42s %-34s %-22s %s\n",
		$r['layer'],
		$r['target'],
		$r['file'] . ':' . $r['line'],
		$r['prop'],
		$r['value'],
		$r['ctx']
	);
}

echo '  total: ' . count( $redeclared ) . PHP_EOL;

/* ------------------------------------------------------------------ *
 * D. HARDCODED VALUES (§55/§57)
 * ------------------------------------------------------------------ *
 * Files that legitimately define the token system are excluded: a literal in
 * `design-tokens.css` / the Theme's `tokens.css` IS the token's value, which is
 * correct. A literal in any CONSUMER file is the bug.
 */
$token_files = array(
	'PLUGIN/assets/css/frontend/design-tokens.css',
	'THEME/assets/css/tokens.css',
);

$literal_checks = array(
	'colour'      => array( 'color', 'background', 'background-color', 'border-color', 'border-top-color', 'border-bottom-color', 'outline-color', 'fill', 'stroke' ),
	'font-family' => array( 'font-family' ),
	'font-size'   => array( 'font-size' ),
	'spacing'     => array( 'padding', 'padding-block', 'padding-inline', 'margin', 'margin-block', 'margin-inline', 'gap', 'row-gap', 'column-gap' ),
	'background'  => array( 'background', 'background-image' ),
	'border'      => array( 'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-block-end' ),
);

echo PHP_EOL . '== D. HARDCODED VALUES OUTSIDE THE TOKEN FILES (§55) ==' . PHP_EOL;

$literal_totals = array();

foreach ( $corpus as $doc ) {

	if ( in_array( $doc['rel'], $token_files, true ) ) {
		continue;
	}

	foreach ( $doc['rules'] as $rule ) {

		foreach ( bb24_declarations( $rule['body'] ) as $prop => $value ) {

			$bucket = null;

			foreach ( $literal_checks as $name => $props ) {
				if ( in_array( $prop, $props, true ) ) {
					$bucket = $name;
					break;
				}
			}

			if ( null === $bucket ) {
				continue;
			}

			/* Tokenised, transparent, or purely structural values are fine. */
			if ( false !== strpos( $value, 'var(' ) ) {
				continue;
			}

			$is_literal =
				( 'colour' === $bucket || 'background' === $bucket || 'border' === $bucket )
					? (bool) preg_match( '/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|\b(black|white|red|blue|green|gray|grey|silver|navy|teal|maroon|orange|purple|gold|beige|ivory|aqua|lime|olive|fuchsia)\b/i', $value )
					: (bool) preg_match( '/\d+(\.\d+)?(px|rem|em|%|vw|vh|pt|ch)\b/', $value );

			if ( ! $is_literal ) {
				continue;
			}

			strtok( $value, '(' );
			$literal_totals[ $bucket ] = ( $literal_totals[ $bucket ] ?? 0 ) + 1;

			printf(
				"  %-11s %-9s %-42s %-34s %-16s %s\n",
				$bucket,
				$doc['layer'],
				$doc['rel'] . ':' . $rule['line'],
				substr( trim( $rule['selector'] ), 0, 34 ),
				$prop,
				substr( $value, 0, 40 )
			);
		}
	}
}

echo PHP_EOL . '  literals by kind:';

foreach ( $literal_totals as $kind => $n ) {
	echo ' ' . $kind . '=' . $n;
}

echo PHP_EOL;

/* ------------------------------------------------------------------ *
 * E. PREVIEW vs LIVE DIVERGENCE (§58/§59)
 * ------------------------------------------------------------------ */

/*
 * The Studio preview is a separate document (an iframe) that loads the
 * PAGE-ADMIN stylesheets; the live site loads the FRONTEND ones. If both layers
 * style the SAME selector with the SAME property, the preview is showing a
 * value the live site will never honour (or vice-versa) unless the values are
 * identical.
 *
 * Only non-@media rules are compared, and only properties that change the
 * VISIBLE result — a preview-only affordance (cursor, outline, user-select)
 * is legitimate and must not be reported, or the report becomes noise.
 */
$visual_props = array(
	'max-width',
	'max-inline-size',
	'min-width',
	'padding',
	'padding-inline',
	'padding-block',
	'margin',
	'margin-inline',
	'margin-block',
	'gap',
	'row-gap',
	'column-gap',
	'background',
	'background-color',
	'background-image',
	'color',
	'font-size',
	'font-family',
	'font-weight',
	'border',
	'border-radius',
	'box-shadow',
	'display',
	'grid-template-columns',
	'flex-direction',
);

echo PHP_EOL . '== E. PREVIEW vs LIVE DIVERGENCE (§58/§59) ==' . PHP_EOL;

$live = array();
$preview = array();

foreach ( $corpus as $doc ) {

	if ( 'FRONTEND' === $doc['layer'] || 'THEME' === $doc['layer'] ) {

		foreach ( $doc['rules'] as $rule ) {

			if ( '' !== $rule['context'] ) {
				continue;
			}

			foreach ( bb24_declarations( $rule['body'] ) as $prop => $value ) {

				if ( ! in_array( $prop, $visual_props, true ) ) {
					continue;
				}

				$sel = trim( $rule['selector'] );

				$live[ $sel ][ $prop ] = array(
					'value' => $value,
					'file'  => $doc['rel'] . ':' . $rule['line'],
					'layer' => $doc['layer'],
				);
			}
		}

		continue;
	}

	if ( 'PREVIEW' !== $doc['layer'] ) {
		continue;
	}

	foreach ( $doc['rules'] as $rule ) {

		if ( '' !== $rule['context'] ) {
			continue;
		}

		foreach ( bb24_declarations( $rule['body'] ) as $prop => $value ) {

			if ( ! in_array( $prop, $visual_props, true ) ) {
				continue;
			}

			$sel = trim( $rule['selector'] );

			$preview[ $sel ][ $prop ] = array(
				'value' => $value,
				'file'  => $doc['rel'] . ':' . $rule['line'],
			);
		}
	}
}

$divergences = 0;

foreach ( $preview as $sel => $props ) {

	if ( ! isset( $live[ $sel ] ) ) {
		continue;
	}

	foreach ( $props as $prop => $p ) {

		if ( ! isset( $live[ $sel ][ $prop ] ) ) {
			continue;
		}

		$l = $live[ $sel ][ $prop ];

		/*
		 * §58 asks for "the same presentation logic" — not identical
		 * characters. Two declarations that read the SAME `--bb-` token are
		 * the same logic even if one spells out a fallback and the other
		 * relies on the token's own default, so comparing raw strings alone
		 * would report a permanent false positive on every corrected rule.
		 * Compare token identity first, then the literal value.
		 */
		$token = static function ( string $value ): ?string {
			return preg_match( '/var\(\s*(--bb-[a-z0-9-]+)/i', $value, $m ) ? strtolower( $m[1] ) : null;
		};

		$p_token = $token( $p['value'] );
		$l_token = $token( $l['value'] );

		if ( null !== $p_token && $p_token === $l_token ) {
			continue;
		}

		if ( $p['value'] === $l['value'] ) {
			continue;
		}

		$divergences++;

		printf(
			"  %-30s %-18s preview=%-28s (%s)\n  %-30s %-18s live   =%-28s (%s)\n",
			substr( $sel, 0, 30 ),
			$prop,
			substr( $p['value'], 0, 28 ),
			$p['file'],
			'',
			'',
			substr( $l['value'], 0, 28 ),
			$l['file']
		);
	}
}

echo '  shared selectors: ' . count( array_intersect_key( $preview, $live ) ) . PHP_EOL;
echo '  divergences: ' . $divergences . PHP_EOL;

/* ------------------------------------------------------------------ *
 * F. UNWANTED BORDERS (§57)
 * ------------------------------------------------------------------ */
echo PHP_EOL . '== F. LITERAL BORDERS ON STRUCTURAL SELECTORS (§57) ==' . PHP_EOL;

$border_count = 0;

foreach ( $corpus as $doc ) {

	if ( in_array( $doc['rel'], $token_files, true ) ) {
		continue;
	}

	foreach ( $doc['rules'] as $rule ) {

		$target = $matches_frame( $rule['selector'] );

		if ( null === $target ) {
			continue;
		}

		foreach ( bb24_declarations( $rule['body'] ) as $prop => $value ) {

			if ( 0 !== strpos( $prop, 'border' ) ) {
				continue;
			}

			if ( false !== strpos( $value, 'var(' ) || '0' === trim( $value ) || 'none' === trim( $value ) ) {
				continue;
			}

			$border_count++;

			printf(
				"  %-9s %-22s %-42s %-18s %s\n",
				$doc['layer'],
				$target,
				$doc['rel'] . ':' . $rule['line'],
				$prop,
				$value
			);
		}
	}
}

echo '  total: ' . $border_count . PHP_EOL;

/* ------------------------------------------------------------------ *
 * ENVIRONMENT
 * ------------------------------------------------------------------ */
echo PHP_EOL . '== ENVIRONMENT ==' . PHP_EOL;
printf(
	'  theme: %s | site: %d | business type: %s' . PHP_EOL,
	get_stylesheet(),
	get_current_blog_id(),
	function_exists( 'bb_get_business_type' ) ? (string) bb_get_business_type() : 'n/a'
);
