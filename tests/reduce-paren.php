<?php
/* Reduce the extra ')' on the add_screen callback line to the correct count. */
$file = $argv[1];
$lines = file( $file );
$p = chr( 41 );

foreach ( $lines as $i => $line ) {

    if ( strpos( $line, "add_screen( self::PAGE_SLUG" ) === false ) {
        continue;
    }

    // Normalise everything after 'render_page\'' to exactly two ')' + ';'.
    $lines[ $i ] = preg_replace(
        "/render_page'[ )]+;/",
        "render_page' ) " . $p . " );",
        $line
    );
}

file_put_contents( $file, implode( '', $lines ) );
echo "done" . PHP_EOL;
