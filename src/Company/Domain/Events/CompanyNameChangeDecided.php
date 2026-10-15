<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin decided a name change request: `approved` (the company now has the new name) or `rejected` (with the note the company reads). */
final readonly class CompanyNameChangeDecided implements DomainEvent
{
    public const string APPROVED = 'approved';

    public const string REJECTED = 'rejected';

    public function __construct(
        public int $companyId,
        public int $requestId,
        public string $decision,
        public string $currentName,
        public string $newName,
        public ?string $note,
        public int $adminId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
