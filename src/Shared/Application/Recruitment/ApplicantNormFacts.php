<?php

namespace Src\Shared\Application\Recruitment;

/** What a psychological test's norm groups look at in an applicant: the age (whole years) the application's profile snapshot carries and the rank of the highest education level; each is null when the application does not carry it. */
final readonly class ApplicantNormFacts
{
    public function __construct(public ?int $age = null, public ?int $educationRank = null) {}
}
