<?php

namespace Src\Shared\Application\JobInvitation;

/** Lets other contexts ask who was invited to which job, without reading JobInvitation's tables. Provided by JobInvitation. */
interface InvitedCandidates
{
    /**
     * Among these jobs, the ones the job seeker was invited to.
     *
     * @param  list<int>  $jobIds
     * @return list<int>
     */
    public function jobIdsOf(int $userId, array $jobIds): array;

    /**
     * Among these jobs, the ones each of these job seekers was invited to; a job seeker with none is missing. One read for the whole list.
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $jobIds
     * @return array<int, list<int>> job seeker id => job ids
     */
    public function jobIdsOfUsers(array $userIds, array $jobIds): array;

    /**
     * Among these job seekers, the ones invited to the job.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function userIdsOf(int $jobId, array $userIds): array;
}
