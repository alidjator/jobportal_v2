<?php

namespace Src\Company\Application\Team;

use Src\Shared\Application\Audit\ActivityEntry;

/** One action of a member from the audit log, with the title of the object it was on when that object still exists. IP address and user agent stay in the entry. */
final readonly class ActivityRow
{
    public function __construct(
        public ActivityEntry $entry,
        public ?string $subjectTitle,
    ) {}
}
