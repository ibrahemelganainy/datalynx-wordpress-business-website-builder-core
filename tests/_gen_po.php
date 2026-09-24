<?php
/**
 * Dev-only .po generator.
 *
 *  1. Scans every plugin PHP file (excluding /tests/) for translatable
 *     strings in the 'business-builder' domain.
 *  2. Produces languages/business-builder-ar.po with one entry per
 *     (context, msgid) — correctly handling msgctxt and plurals — and
 *     fills msgstr from the translation map in _translations.php.
 *
 * Run:  php tests/_gen_po.php
 */

$root = realpath( __DIR__ . '/..' );

/* ---- 1. Collect ---------------------------------------------------- */

$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$files = array();
foreach ( $rii as $file ) {
    if ( ! $file->isFile() || strtolower( $file->getExtension() ) !== 'php' ) {
        continue;
    }
    $path = str_replace( '\\', '/', $file->getPathname() );
    if ( strpos( $path, '/tests/' ) !== false ) {
        continue;
    }
    $files[] = $path;
}
sort( $files );

$entries = array(); // key => [msgctxt, msgid, msgid_plural, references[]]

/**
 * Parse the arguments of a WP translation call at a given position.
 * Returns array of string literals found before the domain argument.
 */
function bb_call_args( string $src, int $pos ): array {
    // Find the opening paren after the function name.
    $open = strpos( $src, '(', $pos );
    if ( false === $open ) {
        return array();
    }
    $depth = 0;
    $arg   = '';
    $args  = array();
    $i     = $open;
    $len   = strlen( $src );
    $in    = null; // current quote char
    for ( ; $i < $len; $i++ ) {
        $c = $src[ $i ];
        if ( null === $in ) {
            if ( '(' === $c ) {
                $depth++;
                continue;
            }
            if ( ')' === $c ) {
                $depth--;
                if ( 0 === $depth ) {
                    $args[] = trim( $arg );
                    break;
                }
                continue;
            }
            if ( ( '"' === $c || "'" === $c ) && 1 === $depth ) {
                $in = $c;
                $arg .= $c;
                continue;
            }
            if ( 1 === $depth && in_array( $c, array( ',', ' ' ), true ) ) {
                if ( '' !== trim( $arg ) ) {
                    $args[] = trim( $arg );
                    $arg = '';
                }
                continue;
            }
            if ( 1 === $depth ) {
                $arg .= $c;
            }
        } else {
            $arg .= $c;
            if ( $c === $in && '\\' !== $src[ $i - 1 ] ) {
                $in = null;
            }
        }
    }
    return $args;
}

function bb_unquote( string $token ): ?string {
    $token = trim( $token );
    if ( strlen( $token ) < 2 ) {
        return null;
    }
    $q = $token[0];
    if ( ( '"' !== $q && "'" !== $q ) || substr( $token, -1 ) !== $q ) {
        return null;
    }
    return stripcslashes( substr( $token, 1, -1 ) );
}

$funcs = array( '__', '_e', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e', '_x', '_ex', '_n', '_nx' );

foreach ( $files as $file ) {
    $src = file_get_contents( $file );
    $rel = str_replace( str_replace( '\\', '/', $root ) . '/', '', $file );

    foreach ( $funcs as $fn ) {
        $offset = 0;
        while ( false !== ( $p = strpos( $src, $fn . '(', $offset ) ) ) {
            // Ensure it's a real function call (preceded by non-identifier char).
            $before = $p > 0 ? $src[ $p - 1 ] : ' ';
            if ( preg_match( '/[A-Za-z0-9_$]/', $before ) ) {
                $offset = $p + 1;
                continue;
            }
            $offset = $p + strlen( $fn ) + 1;
            $args   = bb_call_args( $src, $p );
            if ( empty( $args ) ) {
                continue;
            }

            if ( '_n' === $fn || '_nx' === $fn ) {
                $single = bb_unquote( $args[0] ?? '' );
                $plural = bb_unquote( $args[1] ?? '' );
                if ( null === $single || '' === $single ) {
                    continue;
                }
                $ctx = '_nx' === $fn ? ( bb_unquote( $args[3] ?? '' ) ?? '' ) : '';
                $key = 'p|' . $ctx . '|' . $single . '|' . ( $plural ?? '' );
                if ( ! isset( $entries[ $key ] ) ) {
                    $entries[ $key ] = array( 'ctxt' => $ctx, 'id' => $single, 'plural' => $plural ?? '', 'refs' => array() );
                }
                $entries[ $key ]['refs'][ $rel ] = true;
                continue;
            }

            if ( '_x' === $fn || '_ex' === $fn ) {
                $id  = bb_unquote( $args[0] ?? '' );
                $ctx = bb_unquote( $args[1] ?? '' );
                if ( null === $id || null === $ctx || '' === $id ) {
                    continue;
                }
                $key = 'c|' . $ctx . '|' . $id;
                if ( ! isset( $entries[ $key ] ) ) {
                    $entries[ $key ] = array( 'ctxt' => $ctx, 'id' => $id, 'plural' => '', 'refs' => array() );
                }
                $entries[ $key ]['refs'][ $rel ] = true;
                continue;
            }

            $id = bb_unquote( $args[0] ?? '' );
            if ( null === $id || '' === $id ) {
                continue;
            }
            $key = 's||' . $id;
            if ( ! isset( $entries[ $key ] ) ) {
                $entries[ $key ] = array( 'ctxt' => '', 'id' => $id, 'plural' => '', 'refs' => array() );
            }
            $entries[ $key ]['refs'][ $rel ] = true;
        }
    }
}

/* ---- 2. Load translation map -------------------------------------- */

$map = require __DIR__ . '/_translations.php'; // [id] => ar  (or [id] => [singular, plural...])

/* ---- 3. Emit .po -------------------------------------------------- */

$po  = "# Arabic translation for Business Builder Core.\n";
$po .= "# This file is generated from the plugin source by tests/_gen_po.php.\n";
$po .= "# Do not hand-edit: update the source or the translations map instead.\n";
$po .= "msgid \"\"\n";
$po .= "msgstr \"\"\n";
$po .= "\"Project-Id-Version: Business Builder Core 1.0.0\\n\"\n";
$po .= "\"Report-Msgid-Bugs-To: \\n\"\n";
$po .= "\"Language: ar\\n\"\n";
$po .= "\"MIME-Version: 1.0\\n\"\n";
$po .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$po .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$po .= "\"Plural-Forms: nplurals=6; plural=n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5;\\n\"\n";

global $missing;
$missing = array();

/**
 * Escape a string for a .po msgid/msgstr line.
 */
function bb_po_escape( string $s ): string {
    $s = str_replace( '\\', '\\\\', $s );
    $s = str_replace( '"', '\\"', $s );
    $s = str_replace( "\n", '\\n', $s );
    $s = str_replace( "\t", '\\t', $s );
    return $s;
}

function bb_po_line( string $prefix, string $value ): string {
    return $prefix . ' "' . bb_po_escape( $value ) . "\"\n";
}

// Stable order: context entries, then singles, then plurals; each sorted.
$singles = array();
$contexts = array();
$plurals = array();
foreach ( $entries as $key => $e ) {
    $refs = array_keys( $e['refs'] );
    sort( $refs );
    $e['refs'] = $refs;
    if ( '' !== $e['plural'] ) {
        $plurals[] = $e;
    } elseif ( '' !== $e['ctxt'] ) {
        $contexts[] = $e;
    } else {
        $singles[] = $e;
    }
}
$byid = function ( $a, $b ) {
    return strcmp( $a['ctxt'] . $a['id'], $b['ctxt'] . $b['id'] );
};
usort( $singles, $byid );
usort( $contexts, $byid );
usort( $plurals, $byid );

$all = array_merge( $singles, $contexts, $plurals );

foreach ( $all as $e ) {
    $po .= "\n";
    foreach ( $e['refs'] as $ref ) {
        $po .= '#: ' . $ref . "\n";
    }
    if ( '' !== $e['ctxt'] ) {
        $po .= bb_po_line( 'msgctxt', $e['ctxt'] );
    }
    $po .= bb_po_line( 'msgid', $e['id'] );

    if ( '' !== $e['plural'] ) {
        $po .= bb_po_line( 'msgid_plural', $e['plural'] );
        $tr = $map[ $e['id'] ] ?? null;
        if ( is_array( $tr ) ) {
            foreach ( range( 0, 5 ) as $form ) {
                $val = $tr[ $form ] ?? ( $tr[0] ?? '' );
                $po .= bb_po_line( 'msgstr[' . $form . ']', (string) $val );
            }
        } else {
            // Unknown plural translation: emit 6 empty forms and record it.
            foreach ( range( 0, 5 ) as $form ) {
                $po .= 'msgstr[' . $form . "] \"\"\n";
            }
            $missing[] = $e['id'];
        }
    } else {
        $tr = $map[ $e['id'] ] ?? null;
        if ( is_array( $tr ) ) {
            $tr = $tr[0] ?? '';
        }
        if ( null === $tr ) {
            $po .= "msgstr \"\"\n";
            $missing[] = $e['id'];
        } else {
            $po .= bb_po_line( 'msgstr', (string) $tr );
        }
    }
}

file_put_contents( $root . '/languages/business-builder-ar.po', $po );

echo 'entries: ' . count( $all ) . PHP_EOL;
echo 'missing translations: ' . count( $missing ) . PHP_EOL;
foreach ( $missing as $m ) {
    echo '  UNTRANSLATED: ' . $m . PHP_EOL;
}
