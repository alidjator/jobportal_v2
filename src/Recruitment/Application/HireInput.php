<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** What a recruiter enters to hire an application: the join date (required, checked by the move) and a note for the candidate. */
final readonly class HireInput
{
    public function __construct(public ?DateTimeImmutable $joinDate, public ?string $note) {}
}
