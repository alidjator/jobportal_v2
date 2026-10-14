<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\SenderSide;

/** The caller of a messaging action: a job seeker, or a member of an active company (with their role). */
final readonly class Participant
{
    public function __construct(
        public SenderSide $side,
        public int $userId,
        public ?int $companyId = null,
        public bool $seesAllJobs = false,
    ) {}

    public function isCompany(): bool
    {
        return $this->side === SenderSide::Company;
    }
}
