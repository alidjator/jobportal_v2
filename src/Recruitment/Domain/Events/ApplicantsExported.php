<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * A company member exported the applicants of a job. `filters` names the list filters that were used (stage id and type, must-have flag); the search text is
 * left out because it is a piece of a person's name. The count is the number of rows in the file.
 */
final readonly class ApplicantsExported implements DomainEvent
{
    /** @param  array<string, bool|int|string>  $filters */
    public function __construct(
        public int $jobId,
        public int $companyId,
        public int $userId,
        public array $filters,
        public int $rowCount,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
