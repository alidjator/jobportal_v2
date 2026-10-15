<?php

namespace Src\Company\Application\Activity;

use Src\Shared\Application\Audit\ActivityEntry;

/** One entry of the company activity log with the name of its actor (null for a platform admin or the system) and the title of its subject while it exists. */
final readonly class CompanyActivityRow
{
    public function __construct(
        public ActivityEntry $entry,
        public ?string $actorName,
        public ?string $subjectTitle,
    ) {}
}
