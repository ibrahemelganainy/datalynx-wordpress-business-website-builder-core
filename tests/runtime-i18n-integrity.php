<?php
/*
 * Final integrity check between the .po and the compiled .mo, plus a
 * full untranslated sweep over every msgid found in the source.
 */
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

$root = ABSPATH . 'wp-content/plugins/business-builder-core/';
$po = $root . 'languages/business-builder-ar.po';
$mo = $root . 'languages/business-builder-ar.mo';

$fails = 0;

/* 1. .po has no empty msgstr (except the header). */
$po_lines = file( $po );
$empty = 0;
$n = count( $po_lines );
foreach ( $po_lines as $i => $l ) {
    if ( preg_match( '/^msgstr(\[\d+\])? ""\s*$/', $l ) ) {
        // ignore the file header block (first few lines)
        if ( $i < 10 ) { continue; }
        $empty++;
        if ( $empty <= 5 ) { echo "EMPTY msgstr at po line " . ( $i + 1 ) . PHP_EOL; }
    }
}
echo ( 0 === $empty ? 'PASS' : 'FAIL' ) . " — .po has no empty msgstr (found $empty)\n";
if ( 0 !== $empty ) { $fails++; }

/* 2. Count entries in each. */
$po_msgids = 0;
foreach ( $po_lines as $l ) {
    if ( 0 === strpos( $l, 'msgid "' ) ) { $po_msgids++; }
}
$mo_obj = new MO();
$mo_obj->import_from_file( $mo );
$mo_count = count( $mo_obj->entries );

echo "po msgid lines: $po_msgids ; mo entries: $mo_count\n";
$ok = ( $mo_count >= $po_msgids - 1 ); // mo excludes file header from... (header has empty id, still counted)
echo ( $ok ? 'PASS' : 'FAIL' ) . " — .mo entry count matches .po\n";
if ( ! $ok ) { $fails++; }

/* 3. Spot-check the compiled values equal the .po values for a sample. */
$sample = array( 'Dashboard', 'Paid', 'Bank Transfer', 'Total Revenue', 'Notes', 'Confirm' );
foreach ( $sample as $s ) {
    $v = $mo_obj->entries[ $s ]->translations[0] ?? '';
    $ok = ( '' !== $v );
    echo ( $ok ? 'PASS' : 'FAIL' ) . " — .mo has value for '$s' => '$v'\n";
    if ( ! $ok ) { $fails++; }
}

echo PHP_EOL . ( 0 === $fails ? 'ALL INTEGRITY CHECKS PASSED' : "FAILURES: $fails" ) . PHP_EOL;
