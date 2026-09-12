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

    public function get_description(): string {
        return __( 'Card payments via Stripe (Visa, Mastercard, Amex). API integration not enabled on this site yet.', 'business-builder' );
    }

    public function get_supported_currencies(): array {
        return array( 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'CAD', 'AUD' );
    }

    public function get_settings_schema(): array {

        return array(
            'mode' => array(
                'label'   => __( 'Mode', 'business-builder' ),
                'type'    => 'select',
                'default' => 'test',
                'options' => array(
                    'test' => __( 'Test', 'business-builder' ),
                    'live' => __( 'Live', 'business-builder' ),
                ),
            ),
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
