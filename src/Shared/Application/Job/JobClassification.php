<?php

namespace Src\Shared\Application\Job;

/** How a job is classified, for another context that shows it to someone who is not on the job's company team: the title with the names of its level and its category in the language asked. */
final readonly class JobClassification
{
    public function __construct(public string $title, public ?string $level, public ?string $category) {}
}
