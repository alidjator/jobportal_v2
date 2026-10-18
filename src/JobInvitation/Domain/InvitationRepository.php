<?php

namespace Src\JobInvitation\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

interface InvitationRepository
{
    /** @throws StateConflict `already-invited` when the candidate already has an invitation to the job */
    public function add(Invitation $invitation): Invitation;

    public function save(Invitation $invitation): void;

    public function exists(int $jobId, int $userId): bool;

    public function findOfUser(int $userId, int $id, bool $forUpdate = false): ?Invitation;

    public function findForJobAndUser(int $jobId, int $userId, bool $forUpdate = false): ?Invitation;

    /**
     * The latest invitation the company sent to each of these job seekers; one never invited is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, Invitation> by job seeker id
     */
    public function latestOfCompany(int $companyId, array $userIds): array;

    /** How many invitations the company has sent since the moment. */
    public function countOfCompanySince(int $companyId, DateTimeImmutable $since): int;

    /**
     * @return Page<Invitation> the newest first
     */
    public function ofJob(int $jobId, ?InvitationStatus $status, PageRequest $request): Page;

    /**
     * @return Page<Invitation> the newest first
     */
    public function ofUser(int $userId, ?InvitationStatus $status, PageRequest $request): Page;

    public function deleteOfUser(int $userId): void;
}
