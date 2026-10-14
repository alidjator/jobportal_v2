<?php

namespace Src\JobInvitation\Application;

/** A member of an active company who invites candidates (holds `candidates.search`); `seesAllJobs` is `jobs.view_all`. */
final readonly class InvitingMember
{
    public function __construct(public int $userId, public int $companyId, public bool $seesAllJobs) {}
}
