<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A company member exported the hires of a period (Jakarta days, `from` to `to`), of one job or of every job they may see. The count is the number of rows in the file. */
final readonly class HiresExported implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $from,
        public string $to,
        public ?int $jobId,
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
