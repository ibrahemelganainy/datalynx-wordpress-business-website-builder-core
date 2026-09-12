<?php

namespace BusinessBuilderCore\Core\Payments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Result of verifying a payment.
 */
class PaymentResult {

    /**
     * Whether the payment was verified as successful server-side.
     */
    public bool $success = false;

    /**
     * Resulting status: paid|pending|failed|refunded.
     */
    public string $status = 'pending';

    /**
     * Provider reference.
     */
    public string $reference = '';

    /**
     * Amount reported by the provider (for cross-checking).
     */
    public string $amount = '';

    /**
     * Currency reported by the provider.
     */
    public string $currency = '';

    /**
     * Human message / reason.
     */
    public string $message = '';

    /**
     * Constructor.
     *
     * @param bool   $success   Verified success.
     * @param string $status    Status.
     * @param string $reference Reference.
     * @param string $message   Message.
     */
    public function __construct(
        bool $success = false,
        string $status = 'pending',
        string $reference = '',
        string $message = ''
    ) {
        $this->success   = $success;
        $this->status    = $status;
        $this->reference = $reference;
        $this->message   = $message;
    }
}
