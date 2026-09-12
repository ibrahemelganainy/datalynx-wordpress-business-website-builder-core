<?php

namespace BusinessBuilderCore\Core\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Immutable-ish notification value object.
 *
 * Carries everything a channel needs to deliver one notification,
 * plus an idempotency key so repeated events (e.g. webhooks) cannot
 * produce duplicate notifications (spec 14).
 */
class Notification {

    /**
     * Event type, e.g. 'consultation.new'.
     */
    protected string $event;

    /**
     * Human subject / title.
     */
    protected string $subject;

    /**
     * Body text.
     */
    protected string $message;

    /**
     * Optional recipient email. Empty means "site default recipient".
     */
    protected string $recipient;

    /**
     * Related object id (consultation, appointment, ...).
     */
    protected int $object_id;

    /**
     * Idempotency key. Same key => processed once.
     */
    protected string $dedupe_key;

    /**
     * Extra data for channels/templates.
     *
     * @var array<string, mixed>
     */
    protected array $data;

    /**
     * Constructor.
     *
     * @param string $event      Event type.
     * @param string $subject    Subject/title.
     * @param string $message    Body.
     * @param string $recipient  Recipient email ('' = site default).
     * @param int    $object_id  Related object id.
     * @param string $dedupe_key Idempotency key.
     * @param array  $data       Extra data.
     */
    public function __construct(
        string $event,
        string $subject,
        string $message,
        string $recipient = '',
        int $object_id = 0,
        string $dedupe_key = '',
        array $data = array()
    ) {
        $this->event      = $event;
        $this->subject    = $subject;
        $this->message    = $message;
        $this->recipient  = $recipient;
        $this->object_id  = $object_id;
        $this->dedupe_key = $dedupe_key;
        $this->data       = $data;
    }

    /**
     * Event type.
     */
    public function event(): string {
        return $this->event;
    }

    /**
     * Subject / title.
     */
    public function subject(): string {
        return $this->subject;
    }

    /**
     * Message body.
     */
    public function message(): string {
        return $this->message;
    }

    /**
     * Recipient email ('' = site default).
     */
    public function recipient(): string {
        return $this->recipient;
    }

    /**
     * Related object id.
     */
    public function object_id(): int {
        return $this->object_id;
    }

    /**
     * Idempotency key.
     */
    public function dedupe_key(): string {
        return $this->dedupe_key;
    }

    /**
     * Extra data.
     *
     * @return array<string, mixed>
     */
    public function data(): array {
        return $this->data;
    }
}
