<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** One hire of the export: the person as the snapshot shows them (null fields once the account was anonymised, `isDeleted`), the hire details and the offer the candidate accepted, if any. `checksCompleted` is null when the application has no pre-employment check that counts (cancelled ones do not), else whether every one is completed or waived. */
final readonly class HireExportRow
{
    public function __construct(
        public int $applicationId,
        public int $jobId,
        public DateTimeImmutable $hiredAt,
        public ?DateTimeImmutable $joinDate,
        public bool $isDeleted,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $city,
        public ?HireExportOffer $offer,
        public ?bool $checksCompleted = null,
    ) {}
}
