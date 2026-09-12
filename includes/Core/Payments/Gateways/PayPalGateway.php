<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PayPal gateway. Configuration ready; API integration NOT implemented.
 */
class PayPalGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'paypal';
    }

    public function get_name(): string {
        return 'PayPal';
    }

    public function get_description(): string {
        return __( 'Global payments with a PayPal account or card. API integration not enabled on this site yet.', 'business-builder' );
    }

    public function get_supported_currencies(): array {
        return array( 'USD', 'EUR', 'GBP', 'SAR', 'AED', 'CAD', 'AUD', 'EGP' );
    }

    public function get_settings_schema(): array {

        return array(
            'client_id' => array(
                'label'    => __( 'Client ID', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'client_secret' => array(
                'label'    => __( 'Client Secret', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
            'mode' => array(
                'label'    => __( 'Mode', 'business-builder' ),
                'type'     => 'select',
                'required' => true,
                'secret'   => false,
                'options'  => array(
                    'sandbox' => __( 'Sandbox', 'business-builder' ),
                    'live'    => __( 'Live', 'business-builder' ),
                ),
            ),
        );
    }
}
