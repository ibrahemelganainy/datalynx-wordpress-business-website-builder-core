<?php
/*
 * One-off: insert the payment context block into the consultation
 * template after the $bb_status line, by line number. Deleted after use.
 */
$path  = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/templates/consultation-form.php';
$lines = file( $path );

$has_crlf = false !== strpos( implode( '', $lines ), "\r\n" );
$nl = $has_crlf ? "\r\n" : "\n";

/* Find the line that closes the $bb_status ternary: "    : '';" */
$insert_after = null;

foreach ( $lines as $i => $line ) {
    $match = false !== strpos( $line, "\$bb_status = isset( \$_GET['bb_consult'] )" );

    if ( $match ) {
        /* the closing line is two lines below */
        $insert_after = $i + 2;
        break;
    }
}

if ( null === $insert_after ) {
    echo "anchor not found\n";
    exit( 1 );
}

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

array_splice( $lines, $insert_after, 0, array( $block ) );

file_put_contents( $path, implode( '', $lines ) );
echo "inserted after line " . ( $insert_after + 1 ) . "\n";
