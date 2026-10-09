<?php

namespace Src\Messaging\Application;

/** What happened to one application of a bulk message: `sent` (with the conversation) or `skipped` (with the reason). */
final readonly class BulkMessageResult
{
    private function __construct(
        public int $applicationId,
        public string $status,
        public ?string $reason,
        public ?int $conversationId,
    ) {}

    public static function sent(int $applicationId, int $conversationId): self
    {
        return new self($applicationId, 'sent', null, $conversationId);
    }

    public static function skipped(int $applicationId, string $reason): self
    {
        return new self($applicationId, 'skipped', $reason, null);
    }
}
