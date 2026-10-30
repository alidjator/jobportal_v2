<?php

namespace Src\Company\Application\Activity;

use Src\Shared\Application\Audit\ActivityEntry;

/**
 * One entry of the company activity log with the name of its actor (null for a platform admin or the system), the title of its subject while it exists and the name of the member the action was about
 * (`targetName`: the account named by the entry's `user_id` or `delegate_user_id`, other than the actor).
 */
final readonly class CompanyActivityRow
{
    public function __construct(
        public ActivityEntry $entry,
        public ?string $actorName,
        public ?string $subjectTitle,
        public ?string $targetName = null,
    ) {}
}
