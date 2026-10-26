<?php

namespace Src\Shared\Application\Candidate;

/** A job seeker as another context may name them: the account, the permanent slug of the profile (the id used in the company API) the name and whether they have a profile picture (`GET company/candidates/{slug}/avatar`). */
final readonly class CandidateRef
{
    public function __construct(public int $userId, public string $slug, public string $name, public bool $hasAvatar = false) {}
}
