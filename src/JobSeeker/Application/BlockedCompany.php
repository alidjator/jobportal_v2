<?php

namespace Src\JobSeeker\Application;

use DateTimeImmutable;

/** A company the job seeker blocked, with the name and slug a client shows. */
final readonly class BlockedCompany
{
    public function __construct(public int $companyId, public string $name, public string $slug, public DateTimeImmutable $blockedAt) {}
}
