<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\CheckType;

/** A check with its type, as the team reads it: `outcomeVisible` is true for a member with `checks.manage`; the others see the status and the schedule only ("Selesai" once it is done). */
final readonly class CheckView
{
    public function __construct(public ApplicationCheck $check, public CheckType $type, public bool $outcomeVisible) {}
}
