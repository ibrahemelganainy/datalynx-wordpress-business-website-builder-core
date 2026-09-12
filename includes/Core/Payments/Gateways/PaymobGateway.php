<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Paymob gateway. Configuration ready; API integration NOT implemented.
 */
class PaymobGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'paymob';
    }

    public function get_name(): string {
        return 'Paymob';
    }

    public function get_settings_schema(): array {

        return array(
            'api_key' => array(
                'label'    => __( 'API Key', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
            'integration_id' => array(
                'label'    => __( 'Integration ID', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'iframe_id' => array(
                'label'    => __( 'Iframe ID', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'hmac_secret' => array(
                'label'    => __( 'HMAC Secret', 'business-builder' ),
                'type'     => 'password',
                'required' => false,
                'secret'   => true,
            ),
        );
    }
}
