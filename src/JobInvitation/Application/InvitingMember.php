<?php

namespace Src\JobInvitation\Application;

/** A member of an active company who invites candidates. */
final readonly class InvitingMember
{
    public function __construct(public int $userId, public int $companyId, public bool $isCompanyAdmin) {}
}
