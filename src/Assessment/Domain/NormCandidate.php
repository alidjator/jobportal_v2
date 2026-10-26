<?php

namespace Src\Assessment\Domain;

/** What a norm group looks at in a candidate: the age and the highest education rank at the time of applying. Either is null when the application did not carry it; a norm row that restricts it then does not apply. */
final readonly class NormCandidate
{
    public function __construct(public ?int $age = null, public ?int $educationRank = null) {}
}
