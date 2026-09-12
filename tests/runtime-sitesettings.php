<?php
/** Real WordPress runtime test: SiteSettings consultation payment keys. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Settings/SiteSettings.php';

switch_to_blog( 2 );

$s = new BusinessBuilderCore\Settings\SiteSettings();
$all = $s->get_all();

echo 'DEFAULTS require_payment=' . var_export( $all['require_consultation_payment'], true ) . PHP_EOL;
echo 'DEFAULTS fee=' . var_export( $all['consultation_fee'], true ) . PHP_EOL;
echo 'DEFAULTS currency=' . $all['consultation_currency'] . PHP_EOL;

$s->update(
    array(
        'require_consultation_payment' => 1,
        'consultation_fee'             => '1,250.5 USD',
        'consultation_currency'        => 'egp',
    )
);

$a2 = $s->get_all();
echo 'SANITIZED req=' . var_export( $a2['require_consultation_payment'], true ) . PHP_EOL;
echo 'SANITIZED fee=' . var_export( $a2['consultation_fee'], true ) . PHP_EOL;
echo 'SANITIZED currency=' . $a2['consultation_currency'] . PHP_EOL;

/* Restore defaults. */
$s->update(
    array(
        'require_consultation_payment' => 0,
        'consultation_fee'             => '',
        'consultation_currency'        => 'USD',
    )
);

restore_current_blog();
echo 'DONE' . PHP_EOL;
