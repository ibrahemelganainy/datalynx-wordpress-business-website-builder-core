<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\AbstractGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base for API-based gateways that are CONFIGURATION READY but whose
 * live API integration has NOT been implemented/verified in this codebase
 * (spec 35).
 *
 * These classes:
 *   - declare their real credential schema (so admins can configure them);
 *   - store credentials securely via the shared option layer;
 *   - NEVER pretend a payment succeeded.
 *
 * Until a provider is actually implemented + tested, create_payment()
 * returns an 'unavailable' result and verify_payment() returns a failure.
 * This is the honest, safe behaviour the spec requires.
 */
abstract class AbstractApiGateway extends AbstractGateway {

    public function is_manual(): bool {
        return false;
    }

    /**
     * Whether this provider's live API has been implemented AND tested
     * in this codebase. Always false until a real integration is added.
     *
     * @return bool
     */
    public function is_integration_ready(): bool {
        return false;
    }

    public function create_payment( PaymentTransaction $transaction ): array {

        return array(
            'type'    => 'unavailable',
            'message' => sprintf(
                /* translators: %s: gateway name */
                __( '%s is configuration-ready, but its live payment API has not been enabled on this site yet. Please choose another payment method.', 'business-builder' ),
                $this->get_name()
            ),
        );
    }

    public function verify_payment( array $payload ): PaymentResult {

        return new PaymentResult(
            false,
            'pending',
            '',
            sprintf(
                /* translators: %s: gateway name */
                __( '%s webhook verification is not enabled (no verified API integration).', 'business-builder' ),
                $this->get_name()
            )
        );
    }
}
