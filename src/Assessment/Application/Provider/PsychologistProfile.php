<?php

namespace Src\Assessment\Application\Provider;

use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\Psychologist;
use Src\Shared\Application\Accounts\MemberAccountSummary;

/** The signed-in psychologist's own record, their provider and whether they may sign reports today. */
final readonly class PsychologistProfile
{
    public function __construct(
        public Psychologist $psychologist,
        public Provider $provider,
        public ?MemberAccountSummary $account,
        public bool $canSignReports,
    ) {}
}
