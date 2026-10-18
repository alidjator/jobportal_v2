<?php

namespace Src\Shared\Application\JobInvitation;

/** Lets other contexts ask what a company last invited a person to, without reading JobInvitation's tables. Provided by JobInvitation. */
interface LatestInvitations
{
    /**
     * The latest invitation the company sent to each of these job seekers; a job seeker never invited is missing. One read for the whole list.
     *
     * @param  list<int>  $userIds
     * @return array<int, LatestInvitation> by job seeker id
     */
    public function ofCompany(int $companyId, array $userIds): array;
}
