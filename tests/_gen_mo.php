<?php
/**
 * Dev-only .po -> .mo compiler (pure PHP, no gettext dependency).
 *
 * Parses languages/business-builder-ar.po and writes the binary
 * languages/business-builder-ar.mo that WordPress actually reads.
 *
 * Handles: entries, msgctxt, msgid, msgid_plural, msgstr, msgstr[N].
 */

$src = __DIR__ . '/../languages/business-builder-ar.po';
$dst = __DIR__ . '/../languages/business-builder-ar.mo';

if ( ! is_file( $src ) ) {
    fwrite( STDERR, "Missing .po: $src\n" );
    exit( 1 );
}

/**
 * Parse a .po file into a list of entries.
 * Each entry: [ 'ctxt' => ?string, 'id' => string, 'plural' => ?string,
 *               'strs' => array<int|'' , string> ]
 */
function bb_parse_po( string $raw ): array {
    $raw = str_replace( "\r\n", "\n", $raw );
    $lines = explode( "\n", $raw );
    $entries = array();
    $cur = null;
    $mode = null;      // 'ctxt','id','id_plural','str','str_plural'
    $plural_index = 0;

    $append = function ( ?array &$e, string $mode, int $idx, string $val ) {
        if ( 'ctxt' === $mode ) {
            $e['ctxt'] = ( $e['ctxt'] ?? '' ) . $val;
        } elseif ( 'id' === $mode ) {
            $e['id'] = ( $e['id'] ?? '' ) . $val;
        } elseif ( 'id_plural' === $mode ) {
            $e['plural'] = ( $e['plural'] ?? '' ) . $val;
        } elseif ( 'str' === $mode ) {
            $e['strs'][0] = ( $e['strs'][0] ?? '' ) . $val;
        } elseif ( 'str_plural' === $mode ) {
            $e['strs'][ $idx ] = ( $e['strs'][ $idx ] ?? '' ) . $val;
        }
    };

    foreach ( $lines as $line ) {
        $line = rtrim( $line );

        if ( '' === $line || '#' === $line[0] ) {
            continue;
        }

        if ( 'msgctxt' === $line || 0 === strpos( $line, 'msgctxt ' ) ) {
            if ( null !== $cur ) { $entries[] = $cur; }
            $cur = array( 'ctxt' => null, 'id' => null, 'plural' => null, 'strs' => array() );
            $mode = 'ctxt';
            $append( $cur, $mode, 0, bb_unquote_po( substr( $line, 7 ) ) );
            continue;
        }
        if ( 'msgid' === $line || 0 === strpos( $line, 'msgid ' ) ) {
            if ( null !== $cur && null !== $cur['id'] ) { $entries[] = $cur; }
            if ( null === $cur || null !== $cur['id'] ) {
                $cur = array( 'ctxt' => null, 'id' => null, 'plural' => null, 'strs' => array() );
            }
            $mode = 'id';
            $append( $cur, $mode, 0, bb_unquote_po( substr( $line, 5 ) ) );
            continue;
        }
        if ( 0 === strpos( $line, 'msgid_plural ' ) ) {
            $mode = 'id_plural';
            $append( $cur, $mode, 0, bb_unquote_po( substr( $line, 12 ) ) );
            continue;
        }
        if ( 0 === strpos( $line, 'msgstr[' ) ) {
            if ( preg_match( '/^msgstr\[(\d+)\]\s+(.*)$/', $line, $m ) ) {
                $mode = 'str_plural';
                $plural_index = (int) $m[1];
                $append( $cur, $mode, $plural_index, bb_unquote_po( $m[2] ) );
            }
            continue;
        }
        if ( 0 === strpos( $line, 'msgstr ' ) ) {
            $mode = 'str';
            $append( $cur, $mode, 0, bb_unquote_po( substr( $line, 7 ) ) );
            continue;
        }

        // Continuation string line: "...."
        if ( '"' === $line[0] ) {
            $append( $cur, $mode, $plural_index, bb_unquote_po( $line ) );
            continue;
        }
    }
    if ( null !== $cur && null !== $cur['id'] ) {
        $entries[] = $cur;
    }

    return $entries;
}

function bb_unquote_po( string $token ): string {
    $token = trim( $token );
    if ( strlen( $token ) >= 2 && '"' === $token[0] && '"' === substr( $token, -1 ) ) {
        $inner = substr( $token, 1, -1 );
        return str_replace(
            array( '\\n', '\\t', '\\"', '\\\\' ),
            array( "\n", "\t", '"', '\\' ),
            $inner
        );
    }
    return $token;
}

$entries = bb_parse_po( file_get_contents( $src ) );

/* Build the MO structures. */
$headers = array();
$pairs   = array(); // [context."\x04".id , plural?, str(s)] => translation bytes

foreach ( $entries as $e ) {
    $id = (string) ( $e['id'] ?? '' );
    if ( '' === $id ) {
        // Header entry (empty msgid).
        $headers_str = $e['strs'][0] ?? '';
        foreach ( explode( "\n", $headers_str ) as $h ) {
            if ( '' === trim( $h ) || false === strpos( $h, ':' ) ) { continue; }
            list( $k, $v ) = explode( ':', $h, 2 );
            $headers[ trim( $k ) ] = trim( $v );
        }
        continue;
    }

    $key = ( null !== $e['ctxt'] ? $e['ctxt'] . "\x04" : '' ) . $id;

    if ( null !== $e['plural'] ) {
        // Plural: NUL-join the msgstr forms.
        $forms = array();
        for ( $i = 0; $i < 6; $i++ ) {
            $forms[] = $e['strs'][ $i ] ?? ( $e['strs'][0] ?? '' );
        }
        $pairs[ $key ] = implode( "\0", $forms );
    } else {
        $pairs[ $key ] = (string) ( $e['strs'][0] ?? '' );
    }
}

/* Ensure a minimal header so gettext is happy. */
$headers['MIME-Version']         = $headers['MIME-Version'] ?? '1.0';
$headers['Content-Type']         = $headers['Content-Type'] ?? 'text/plain; charset=UTF-8';
$headers['Content-Transfer-Encoding'] = $headers['Content-Transfer-Encoding'] ?? '8bit';
$headers['Language']             = $headers['Language'] ?? 'ar';
$headers['Plural-Forms']         = $headers['Plural-Forms'] ?? 'nplurals=6; plural=n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5;';

$header_block = '';
foreach ( $headers as $k => $v ) {
    $header_block .= $k . ': ' . $v . "\n";
}

/*
 * Build the MO entry map. The header (empty msgid) is handled SEPARATELY and
 * is never part of the sorted set, so it can never collide with or be
 * displaced by a real string. This is the invariant the GNU MO format relies
 * on: entry index 0 is always the empty original at the start of the blob.
 */
$map = $pairs;
if ( isset( $map[''] ) ) {
    unset( $map[''] );
}
uksort( $map, 'strcmp' );

/* Final, ordered key list: header first, then the sorted msgids. */
$keys = array( '' );
foreach ( array_keys( $map ) as $k ) {
    $keys[] = $k;
}
$count = count( $keys );

/* Value lookup: the header maps to its block; every other key to $map. */
$value_for = function ( string $k ) use ( $header_block, $map ): string {
    return ( '' === $k ) ? $header_block : (string) $map[ $k ];
};

/*
 * Layout (standard GNU MO):
 *   header:                  20 bytes (5 uint32: magic, rev, count, O, T)
 *   originals table:         count * (length, offset)
 *   translations table:      count * (length, offset)
 *   originals blob           (NUL-terminated strings)
 *   translations blob        (NUL-terminated strings)
 */
$originals_table_offset    = 28;

$orig_table  = array();
$trans_table = array();

/* Build the tables as explicit byte strings first, so the blob offset is
 * derived from their ACTUAL length — never from an assumed count*8. */
$orig_table_bytes  = '';
$trans_table_bytes = '';

$orig_table  = array();
$trans_table = array();

/* Placeholder offsets; filled in the second pass once the table sizes are known. */
$blob_off = 0;

/*
 * Two-pass build.
 * Pass 1: build the string blobs and remember each entry's length.
 * Pass 2: now that table sizes are known, compute absolute blob offsets.
 */
$orig_blob  = '';
$trans_blob = '';

$orig_lengths  = array();
$trans_lengths = array();
$trans_values  = array();

foreach ( $keys as $k ) {
    $orig_lengths[] = strlen( $k );
    $orig_blob     .= $k . "\0";
}
foreach ( $keys as $k ) {
    $v               = $value_for( $k );
    $trans_values[]  = $v;
    $trans_lengths[] = strlen( $v );
    $trans_blob     .= $v . "\0";
}

$originals_table_offset    = 20;
$translations_table_offset = 20 + count( $keys ) * 8;
$blob_off                  = 20 + count( $keys ) * 16;

/* Pass 2: absolute offsets. Originals blob first, translations blob after it. */
$cursor = $blob_off;
foreach ( $orig_lengths as $len ) {
    $orig_table[] = pack( 'V2', $len, $cursor );
    $cursor      += $len + 1;
}
$cursor = $blob_off + strlen( $orig_blob );
foreach ( $trans_lengths as $len ) {
    $trans_table[] = pack( 'V2', $len, $cursor );
    $cursor       += $len + 1;
}

$out = pack( 'V*', 0x950412de, 0, count( $keys ), $originals_table_offset, $translations_table_offset )
    . implode( '', $orig_table )
    . implode( '', $trans_table )
    . $orig_blob
    . $trans_blob;

file_put_contents( $dst, $out );

echo 'mo entries: ' . $count . PHP_EOL;
echo 'mo bytes: ' . strlen( $out ) . PHP_EOL;

/* ---- Round-trip check with WordPress's own parser. ---- */
if ( class_exists( 'MO' ) ) {
    $mo = new MO();
    $ok = $mo->import_from_file( $dst );
    echo 'round-trip (WP MO): ' . ( $ok ? 'OK' : 'FAILED' ) . ', entries=' . count( $mo->entries ) . PHP_EOL;
    $probe = 'Total Lawyers';
    echo 'probe "' . $probe . '": ' . var_export( $mo->entries[ $probe ]->translations[0] ?? null, true ) . PHP_EOL;
}
