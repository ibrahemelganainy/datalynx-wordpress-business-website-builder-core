<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\AbstractGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * InstaPay gateway (manual).
 *
 * InstaPay is a transfer method without a public transactional API for
 * this scenario, so it is supported as a manual method (spec 17).
 */
class InstaPayGateway extends AbstractGateway {

    public function get_id(): string {
        return 'instapay';
    }

    public function get_name(): string {
        return __( 'InstaPay', 'business-builder' );
    }

    public function get_description(): string {
        return __( 'Instant bank transfer via InstaPay, verified manually. Fully functional.', 'business-builder' );
    }

    public function is_manual(): bool {
        return true;
    }

    public function get_settings_schema(): array {

        return array(
            'instapay_address' => array(
                'label'       => __( 'InstaPay Address', 'business-builder' ),
                'type'        => 'text',
                'required'    => true,
                'secret'      => false,
                'placeholder' => 'name@instapay',
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
            'message' => __( 'Please transfer via InstaPay and submit the reference for verification.', 'business-builder' ),
        );
    }

    public function verify_payment( array $payload ): PaymentResult {

        return new PaymentResult(
            false,
            'pending',
            '',
            __( 'InstaPay payments are verified manually by an administrator.', 'business-builder' )
        );
    }
}
