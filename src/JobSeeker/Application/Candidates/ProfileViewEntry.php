<?php

namespace Src\JobSeeker\Application\Candidates;

use DateTimeImmutable;

/** A company that opened the candidate's profile or a resume. Which member did it is not told. */
final readonly class ProfileViewEntry
{
    public function __construct(public int $companyId, public ?string $companyName, public string $kind, public string $day, public DateTimeImmutable $viewedAt) {}
}
