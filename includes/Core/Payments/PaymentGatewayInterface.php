<?php

namespace BusinessBuilderCore\Core\Payments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Payment gateway contract.
 *
 * A gateway knows how to describe itself, validate its configuration,
 * create a payment, verify it (from a return or webhook), and expose
 * its settings schema so the admin UI renders dynamic fields (spec 5/8).
 *
 * Deliberately provider-agnostic: real API calls for a given provider
 * live inside that provider's implementation, never in Consultation.
 */
interface PaymentGatewayInterface {

    /**
     * Stable gateway id, e.g. 'bank_transfer', 'stripe'.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Human-readable gateway name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Short human-readable description shown on the gateway card.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Browser-safe logo URL for the admin card (local asset, never a
     * fragile remote image for critical UI).
     *
     * @return string
     */
    public function get_logo_url(): string;

    /**
     * Currency codes this gateway supports. An empty array means "any".
     *
     * @return string[]
     */
    public function get_supported_currencies(): array;

    /**
     * Whether the provider's live API is implemented AND tested in this
     * codebase. Gateways that return false are configuration-ready only
     * and must never be presented as production-ready (spec: Part 6).
     *
     * @return bool
     */
    public function is_integration_ready(): bool;

    /**
     * Validate the current configuration, returning human-readable
     * problems (empty when valid). Used by the admin UI and before a
     * customer is sent to the provider.
     *
     * @return string[]
     */
    public function validate_configuration(): array;

    /**
     * Provider payment URL for a transaction, if the gateway is
     * redirect-based. Empty for manual / non-redirect gateways.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    public function get_payment_url( PaymentTransaction $transaction ): string;

    /**
     * Whether this gateway is automatic (API) or manual (offline).
     *
     * Manual gateways are verified by an administrator, never by a
     * server-to-server callback (spec 16).
     *
     * @return bool
     */
    public function is_manual(): bool;

    /**
     * Whether the operator has supplied enough configuration to use it.
     *
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Settings schema for the dynamic admin UI.
     *
     * Each field: key => [
     *   'label','type','required','secret','description','placeholder'
     * ]
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_settings_schema(): array;

    /**
     * Create a payment for a transaction.
     *
     * Returns a redirect URL to the provider, or an array/string
     * describing next steps (e.g. manual instructions). Providers that
     * are not fully implemented return a structured "unavailable" result
     * rather than faking success.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<string, mixed> Result: ['type'=>'redirect'|'manual'|'unavailable', ...]
     */
    public function create_payment( PaymentTransaction $transaction ): array;

    /**
     * Verify a payment from a provider return/webhook payload.
     *
     * Must be server-side and (where supported) cryptographically
     * verified. Never trust a client-supplied "paid" flag.
     *
     * @param array $payload Raw payload (return query or webhook body).
     * @return PaymentResult Verification result.
     */
    public function verify_payment( array $payload ): PaymentResult;
}
