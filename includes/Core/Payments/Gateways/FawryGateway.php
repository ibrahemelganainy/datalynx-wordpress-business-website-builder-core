<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fawry gateway. Configuration ready; API integration NOT implemented.
 */
class FawryGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'fawry';
    }

    public function get_name(): string {
        return 'Fawry';
    }

    public function get_description(): string {
        return __( 'Pay at thousands of Fawry outlets or online in Egypt. API integration not enabled on this site yet.', 'business-builder' );
    }

    public function get_supported_currencies(): array {
        return array( 'EGP' );
    }

    public function get_settings_schema(): array {

        return array(
            'merchant_code' => array(
                'label'    => __( 'Merchant Code', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'security_key' => array(
                'label'    => __( 'Security Key', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
        );
    }
}
