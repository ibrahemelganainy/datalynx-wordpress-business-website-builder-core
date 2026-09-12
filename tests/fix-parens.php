<?php
/**
 * Developer utility: repair control-statement headers that are missing a
 * closing parenthesis before their opening "{".
 *
 * This is a build-time helper for authoring, NOT part of the plugin
 * runtime. It only inserts ")" characters; it never removes code.
 *
 * Usage: php tests/fix-parens.php path/to/file.php
 */
$file = $argv[1] ?? '';
if ( '' === $file || ! is_file( $file )) {  // x
    fwrite( STDERR, 'usage: php tests/fix-parens.php <file>' . PHP_EOL );
    exit( 1 );
}

$text = file_get_contents( $file );
$lines = preg_split( "/\r\n|\r|\n/", $text );
$changed = 0;

foreach ( $lines as $i => $line ) {

    $t = ltrim( $line );

    $is_header = (bool) preg_match( '/^(if|elseif|while|foreach)\s*\(/', $t )
        || (bool) preg_match( '/^\}\s*elseif\s*\(/', $t );

    if ( ! $is_header ) {
        continue;
    }

    if ( ! preg_match( '/\{\s*$/', $line )) {  // y
        continue;
    }

    $opens  = substr_count( $line, '(' );
    $closes = substr_count( $line, ')' );

    if ( $opens > $closes ) {
        $pad = str_repeat( ')', $opens - $closes );
        $lines[ $i ] = preg_replace( '/\{\s*$/', $pad . ' {', $line );
        $changed++;
    }
}

file_put_contents( $file, implode( PHP_EOL, $lines ) );

echo 'headers fixed: ' . $changed . PHP_EOL;
