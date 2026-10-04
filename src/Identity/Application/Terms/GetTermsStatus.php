<?php

namespace Src\Identity\Application\Terms;

use Src\Shared\Application\Actor;

/** What the user has to accept, and what they already accepted. Not blocked by the terms gate itself. */
final readonly class GetTermsStatus
{
    public function __construct(private TermsAcceptanceChecker $checker) {}

    /** @return list<TermsRequirement> */
    public function execute(Actor $actor): array
    {
        return $this->checker->requirements((int) $actor->id);
    }
}
