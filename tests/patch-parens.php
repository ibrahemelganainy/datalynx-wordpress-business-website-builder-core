<?php
/**
 * Developer utility: repair specific "missing ) " patterns in a staged
 * file. Authoring helper only. Inserts ')' characters, never removes code.
 *
 * Usage: php tests/patch-parens.php <file>
 *
 * Each rule is a message "find" and "replace" string. We build the
 * replacement dynamically so the extra ')' can never be lost while
 * editing this file itself.
 */
$file = $argv[1] ?? '';

$p = chr( 41 ); // ')'

if ( '' === $file || ! is_file( $file ) ) {
    fwrite( STDERR, 'usage: php tests/patch-parens.php <file>' . PHP_EOL );
    exit( 1 );
}

$c = file_get_contents( $file );

$rules = array(
    array(
        "if ( ! defined( 'ABSPATH' ) {",
        "if ( ! defined( 'ABSPATH' ) @@ {",
    ),
    array(
        "array( \$this, 'render_page' ));",
        "array( \$this, 'render_page' ) @@ );",
    ),
    array(
        "if ( ! current_user_can( 'manage_options' ) {",
        "if ( ! current_user_can( 'manage_options' ) @@ {",
    ),
    array(
        "<?php if ( isset( \$_GET['updated'] ) : ?>",
        "<?php if ( isset( \$_GET['updated'] ) @@ : ?>",
    ),
    array(
        "<?php if ( ! empty( \$currencies ) : ?>",
        "<?php if ( ! empty( \$currencies ) @@ : ?>",
    ),
    array(
        "<?php if ( function_exists( 'submit_button' ) : ?>",
        "<?php if ( function_exists( 'submit_button' ) @@ : ?>",
    ),
    array(
        "PaymentManager::mask_secret( \$current ) . '</span>';",
        "PaymentManager::mask_secret( \$current ) @@ . '</span>';",
    ),
    array(
        "wp_unslash( \$_POST['currency'] )\n            : '';",
        "wp_unslash( \$_POST['currency'] ) @@\n            : '';",
    ),
    array(
        "if ( '' === \$currency || ! Currencies::exists( \$currency ) {",
        "if ( '' === \$currency || ! Currencies::exists( \$currency ) @@ {",
    ),
    array(
        "gateway( \$gateway_id ) {",
        "gateway( \$gateway_id ) @@ {",
    ),
    array(
        "if ( is_array( \$fields ) {",
        "if ( is_array( \$fields ) @@ {",
    ),
);

$applied = 0;

foreach ( $rules as $rule ) {

    $from = $rule[0];
    $to   = str_replace( '@@', $p, $rule[1] );

    if ( strpos( $c, $from ) !== false ) {
        $c = str_replace( $from, $to, $c );
        $applied++;
    }
}

file_put_contents( $file, $c );

echo 'patched: ' . $applied . PHP_EOL;
