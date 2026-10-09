<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\AlertFrequency;

/** What a job seeker sends to create or replace an alert. The criteria are checked when the alert is built. */
final readonly class JobAlertInput
{
    /** @param  array<string, mixed>  $criteria  the search filters by name */
    public function __construct(
        public string $name,
        public AlertFrequency $frequency,
        public array $criteria,
        public bool $email,
        public bool $inApp,
    ) {}
}
