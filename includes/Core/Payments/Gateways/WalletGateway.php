<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\AbstractGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Electronic Wallet gateway (manual).
 *
 * Many wallets (e.g. Vodafone Cash) have no public, documented API for
 * this use case. Treated as a manual method: show the wallet number and
 * verify manually (spec 17). No invented API.
 */
class WalletGateway extends AbstractGateway {

    public function get_id(): string {
        return 'wallet';
    }

    public function get_name(): string {
        return __( 'Electronic Wallet', 'business-builder' );
    }

    public function get_description(): string {
        return __( 'Mobile wallet transfer (e.g. Vodafone Cash), verified manually. Fully functional.', 'business-builder' );
    }

    public function is_manual(): bool {
        return true;
    }

    public function get_settings_schema(): array {

        return array(
            'wallet_provider' => array(
                'label'       => __( 'Wallet Provider', 'business-builder' ),
                'type'        => 'text',
                'required'    => true,
                'secret'      => false,
                'placeholder' => 'Vodafone Cash',
            ),
            'wallet_number' => array(
                'label'    => __( 'Wallet Number', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'instructions' => array(
                'label'    => __( 'Instructions', 'business-builder' ),
                'type'     => 'textarea',
                'required' => false,
                'secret'   => false,
            ),
        );
    }

    public function create_payment( PaymentTransaction $transaction ): array {

        return array(
            'type'    => 'manual',
            'message' => __( 'Please send the amount to the wallet number below and submit the transfer reference.', 'business-builder' ),
        );
    }

    /**
     * The wallet details the administrator configured, as safe public rows.
     *
     * @return array<string, mixed>
     */
    public function get_public_instructions(): array {

        $rows = array();

        $provider = (string) $this->get_setting( 'wallet_provider', '' );
        $number   = (string) $this->get_setting( 'wallet_number', '' );

        if ( $provider !== '' ) {
            $rows[] = array(
                'label' => __( 'Wallet Provider', 'business-builder' ),
                'value' => $provider,
            );
        }

        if ( $number !== '' ) {
            $rows[] = array(
                'label' => __( 'Wallet Number', 'business-builder' ),
                'value' => $number,
                'copy'  => true,
            );
        }

        return array(
            'rows'         => $rows,
            'instructions' => (string) $this->get_setting( 'instructions', '' ),
            'icon'         => 'wallet',
        );
    }

    public function verify_payment( array $payload ): PaymentResult {

        return new PaymentResult(
            false,
            'pending',
            '',
            __( 'Wallet payments are verified manually by an administrator.', 'business-builder' )
        );
    }
}
