<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\AbstractGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bank Transfer gateway (manual / offline).
 *
 * Fully functional as a manual method: the customer is shown bank
 * instructions and the administrator later verifies the transfer
 * (spec 16). No fake API is used.
 */
class BankTransferGateway extends AbstractGateway {

    public function get_id(): string {
        return 'bank_transfer';
    }

    public function get_name(): string {
        return __( 'Bank Transfer', 'business-builder' );
    }

    public function get_description(): string {
        return __( 'Offline bank transfer, verified manually by an administrator. Fully functional.', 'business-builder' );
    }

    public function is_manual(): bool {
        return true;
    }

    public function get_settings_schema(): array {

        return array(
            'bank_name' => array(
                'label'       => __( 'Bank Name', 'business-builder' ),
                'type'        => 'text',
                'required'    => true,
                'secret'      => false,
                'description' => __( 'The bank the client should transfer to.', 'business-builder' ),
            ),
            'account_name' => array(
                'label'    => __( 'Account Name', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'account_number' => array(
                'label'    => __( 'Account Number / IBAN', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'instructions' => array(
                'label'       => __( 'Transfer Instructions', 'business-builder' ),
                'type'        => 'textarea',
                'required'    => false,
                'secret'      => false,
                'description' => __( 'Shown to the client after they choose bank transfer.', 'business-builder' ),
            ),
        );
    }

    public function create_payment( PaymentTransaction $transaction ): array {

        return array(
            'type'    => 'manual',
            'message' => __( 'Please complete a bank transfer and send us the reference number for verification.', 'business-builder' ),
        );
    }

    /**
     * Manual gateways are never auto-verified from a payload.
     *
     * @param array $payload Ignored.
     * @return PaymentResult
     */
    public function verify_payment( array $payload ): PaymentResult {

        return new PaymentResult(
            false,
            'pending',
            '',
            __( 'Bank transfer payments are verified manually by an administrator.', 'business-builder' )
        );
    }
}
