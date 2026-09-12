<?php
/*
 * One-off: repair the consultation template variable block ordering.
 * Removes the misplaced insert and re-adds it after the closing
 * ": '';" line. Deleted after use.
 */
$path  = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/templates/consultation-form.php';
$lines = file( $path );

$has_crlf = false !== strpos( implode( '', $lines ), "\r\n" );
$nl = $has_crlf ? "\r\n" : "\n";

/* Rebuild the file's header region deterministically (lines 17..38 area). */
$out = array();

foreach ( $lines as $i => $line ) {

    $num = $i + 1;

    /* Drop the misplaced payment block (lines 22..35) and the stray " : '';" line 36. */
    if ( $num >= 22 && $num <= 35 ) {
        continue;
    }

    if ( 36 === $num ) {
        continue;
    }

    $out[] = $line;
}

/*
 * Now insert the payment block right after the (single remaining) line
 * that closes the $bb_status ternary:  "    : '';"
 */
$rebuilt = array();
$inserted = false;

foreach ( $out as $line ) {

    $rebuilt[] = $line;

    $is_close = ( false === $inserted ) && ( false !== strpos( $line, "    : '';" ) );

    if ( $is_close ) {

        $block  = $nl;
        $block .= "/*" . $nl;
        $block .= " * Payment context injected by LawFirmSections::render_consultation_section()." . $nl;
        $block .= " * When absent (e.g. the template is included elsewhere), payment is off." . $nl;
        $block .= " */" . $nl;
        $block .= "\$bb_payment    = isset( \$bb_payment ) && is_array( \$bb_payment ) ? \$bb_payment : array( 'enabled' => false, 'payable' => false, 'fee' => '', 'currency' => '', 'available' => array() );" . $nl;
        $block .= "\$bb_section_id = isset( \$bb_section_id ) ? (string) \$bb_section_id : '';" . $nl;
        $block .= $nl;
        $block .= "\$bb_pay_on      = ! empty( \$bb_payment['payable'] );" . $nl;
        $block .= "\$bb_fee         = isset( \$bb_payment['fee'] ) ? (string) \$bb_payment['fee'] : '';" . $nl;
        $block .= "\$bb_currency    = isset( \$bb_payment['currency'] ) ? (string) \$bb_payment['currency'] : '';" . $nl;
        $block .= "\$bb_gateways    = isset( \$bb_payment['available'] ) && is_array( \$bb_payment['available'] ) ? \$bb_payment['available'] : array();" . $nl;
        $block .= "\$bb_fee_display = '' !== \$bb_fee" . $nl;
        $block .= "    ? \\BusinessBuilderCore\\Core\\Payments\\Currencies::format( (float) \$bb_fee, \$bb_currency )" . $nl;
        $block .= "    : '';" . $nl;

        $rebuilt[] = $block;
        $inserted = true;
    }
}

file_put_contents( $path, implode( '', $rebuilt ) );

echo $inserted ? "rebuilt OK\n" : "closing line not found\n";
