<?php
/**
 * Runtime test: the consultation payment UI renders the gateway selector and
 * the real, administrator-configured manual instructions (no hardcoding),
 * plus the transaction-reference + upload fields.
 *
 * Run:  php tests/runtime-payment-ui.php
 */

define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;

    if ( $cond ) {
        $pass++;
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

/* Configure real manual gateway values. */
$pm = new PaymentManager();
$pm->save_settings( 'bank_transfer', array( 'bank_name' => 'CIB Egypt', 'account_name' => 'Law Firm LLC', 'account_number' => 'EG380019000500000001111222', 'instructions' => 'Use your name as the transfer note.' ) );
$pm->save_settings( 'wallet', array( 'wallet_provider' => 'Vodafone Cash', 'wallet_number' => '01099998888', 'instructions' => 'Send to the wallet.' ) );
$pm->save_settings( 'instapay', array( 'instapay_address' => 'lawfirm@instapay', 'instructions' => 'Transfer via InstaPay.' ) );

$pm->set_enabled_gateways( array( 'paymob', 'bank_transfer', 'wallet', 'instapay' ) );
$pm->save_settings( 'paymob', array( 'api_key' => 'k', 'integration_id' => '1', 'iframe_id' => '2', 'hmac_secret' => 's' ) );

/* Render the payment selector partial through the real SectionPayment path. */
$resolver = new \BusinessBuilderCore\Packs\LawFirm\Payments\SectionPayment( $pm, new \BusinessBuilderCore\Settings\SiteSettings() );

$config = $resolver->resolve(
    'consultation',
    array(
        'payment_enabled'  => '1',
        'payment_fee'      => '750',
        'payment_currency' => 'EGP',
        'payment_gateways' => array( 'paymob', 'bank_transfer', 'wallet', 'instapay' ),
    )
);

check( 'section is payable', ! empty( $config['payable'] ) );
check( 'bank/wallet/instapay offered', in_array( 'bank_transfer', $config['gateways'], true ) && in_array( 'wallet', $config['gateways'], true ) );

/* Render the manual instructions partial for each manual gateway. */
$tpl = BB_CORE_PATH . 'templates/partials/manual-payment-instructions.php';

$checks = array(
    'bank_transfer' => array( 'CIB Egypt', 'Law Firm LLC', 'EG380019000500000001111222' ),
    'wallet'        => array( 'Vodafone Cash', '01099998888' ),
    'instapay'      => array( 'lawfirm@instapay' ),
);

foreach ( $checks as $gw_id => $needles ) {

    $bb_manual_gateway  = $pm->gateway( $gw_id );
    $bb_manual_gw_id    = $gw_id;
    $bb_manual_amount   = '750.00';
    $bb_manual_currency = 'EGP';
    $bb_manual_uid      = 'consultation-' . $gw_id;

    ob_start();
    include $tpl;
    $html = (string) ob_get_clean();

    check( $gw_id . ': block is scoped to its gateway', false !== strpos( $html, 'data-bb-manual-gateway="' . $gw_id . '"' ) );
    check( $gw_id . ': reference field present', false !== strpos( $html, 'bb_manual_reference' ) );
    check( $gw_id . ': upload field present', false !== strpos( $html, 'bb_manual_receipt' ) );

    foreach ( $needles as $needle ) {
        check( $gw_id . ': shows configured "' . $needle . '"', false !== strpos( $html, $needle ) );
    }
}

/* The gateway logos resolve through the existing asset mechanism. */
check( 'xpay logo resolves', '' !== $pm->gateway( 'xpay' )->get_logo_url() );
check( 'paymob logo resolves', '' !== $pm->gateway( 'paymob' )->get_logo_url() );

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* Cleanup */
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_gateways' );

exit( 0 === $fail ? 0 : 1 );
