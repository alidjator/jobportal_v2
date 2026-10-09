<?php

namespace Src\Campaign\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A campaign was `created`, `updated` or `deleted`, by a company admin (`actorKind` `user`) or a platform admin (`admin`). */
final readonly class CampaignChanged implements DomainEvent
{
    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    public function __construct(
        public int $campaignId,
        public ?int $companyId,
        public string $change,
        public string $actorKind,
        public int $actorId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
