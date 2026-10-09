<?php

namespace Src\Shared\Application\Candidate;

/** A job seeker as another context may name them: the account, the permanent slug of the profile (the id used in the company API) and the name. */
final readonly class CandidateRef
{
    public function __construct(public int $userId, public string $slug, public string $name) {}
}
