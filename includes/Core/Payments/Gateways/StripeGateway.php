<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stripe gateway. Configuration ready; API integration NOT implemented.
 */
class StripeGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'stripe';
    }

    public function get_name(): string {
        return 'Stripe';
    }

    public function get_settings_schema(): array {

        return array(
            'publishable_key' => array(
                'label'    => __( 'Publishable Key', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'secret_key' => array(
                'label'    => __( 'Secret Key', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
            'webhook_secret' => array(
                'label'    => __( 'Webhook Signing Secret', 'business-builder' ),
                'type'     => 'password',
                'required' => false,
                'secret'   => true,
            ),
        );
    }
}
