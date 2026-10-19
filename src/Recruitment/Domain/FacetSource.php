<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** An application that has no facets yet, with what its facets are built from. */
final readonly class FacetSource
{
    /** @param  array<string, mixed>  $snapshot */
    public function __construct(
        public int $applicationId,
        public int $companyId,
        public int $jobId,
        public int $userId,
        public DateTimeImmutable $appliedAt,
        public array $snapshot,
        public bool $viaInvitationSource,
    ) {}
}
