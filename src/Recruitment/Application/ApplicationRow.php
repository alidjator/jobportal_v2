<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;

/** The stored facts of one application as a list shows them, before the job and company names are added. */
final readonly class ApplicationRow
{
    public function __construct(
        public int $id,
        public int $jobId,
        public int $companyId,
        public StageType $status,
        public DateTimeImmutable $appliedAt,
        public ?DateTimeImmutable $viewedAt = null,
        /** When the application was imported from karir.pkp; null for one made on this platform. */
        public ?DateTimeImmutable $migratedAt = null,
    ) {}
}
