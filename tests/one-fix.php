<?php
/* One-off: exact byte replacement for the render_page callback line. */
$file = $argv[1];
$c    = file_get_contents( $file );
$p    = chr( 41 );

$sp   = chr( 32 );
$find = "array( \$this, 'render_page' )" . $sp . $p . $sp . $p . ";";
$repl = "array( \$this, 'render_page' )" . $sp . $p . ");";

$count = 0;
$c = str_replace( $find, $repl, $c, $count );

file_put_contents( $file, $c );
echo 'replaced: ' . $count . PHP_EOL;
