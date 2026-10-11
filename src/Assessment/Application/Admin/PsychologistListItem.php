<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Psychologist;
use Src\Shared\Application\Accounts\MemberAccountSummary;

/** A psychologist with the name, email and sign-in facts of their account, and whether they may sign reports today. */
final readonly class PsychologistListItem
{
    public function __construct(
        public Psychologist $psychologist,
        public ?MemberAccountSummary $account,
        public bool $canSignReports,
    ) {}
}
