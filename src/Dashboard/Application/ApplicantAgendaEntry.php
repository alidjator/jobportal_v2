<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Calendar\ApplicantAgendaItem;

/** One date of the agenda of a job seeker, with the names the screen shows. */
final readonly class ApplicantAgendaEntry
{
    public function __construct(public ApplicantAgendaItem $item, public ?string $jobTitle, public ?string $companyName) {}
}
