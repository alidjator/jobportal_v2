<?php

namespace Src\Company\Application\Team;

use Src\Shared\Application\Audit\ActivityEntry;

/**
 * One change of the team from the audit log: the actor (`actorName` is null for a platform admin, who shows without a name), the member or invitation it
 * concerned (name and email, null once the account or the invitation is gone) and the change itself in the entry's metadata. It holds no IP address or user agent.
 */
final readonly class TeamHistoryRow
{
    public function __construct(
        public ActivityEntry $entry,
        public ?string $actorName,
        public ?int $memberUserId,
        public ?string $memberName,
        public ?string $memberEmail,
    ) {}
}
