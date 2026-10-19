<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface InvitationRepository
{
    public function find(int $id, bool $forUpdate = false): ?Invitation;

    /** The application's invitation that is invited or started, if any. */
    public function active(int $applicationId, bool $forUpdate = false): ?Invitation;

    /**
     * The applications of the company that have an invitation invited or started, whichever the invitation; `$jobIds` limits them to those jobs (null: every job).
     *
     * @param  list<int>|null  $jobIds
     * @return list<int>
     */
    public function activeApplicationIds(int $companyId, ?array $jobIds): array;

    /** Inserts a new invitation with its assessors and returns it with its id. */
    public function add(Invitation $invitation): Invitation;

    /** Saves the status, the reminder time and the closing time. */
    public function update(Invitation $invitation): void;

    /**
     * Every invitation of the application, newest first.
     *
     * @return list<Invitation>
     */
    public function ofApplication(int $applicationId): array;

    /**
     * The candidate's invitations, newest first.
     *
     * @return Page<Invitation>
     */
    public function pageOfApplicant(int $userId, ?InvitationStatus $status, PageRequest $request): Page;

    /**
     * The company's submitted invitations whose result waits for scoring and where the member is an assessor, the longest waiting first (the set of the `my_scoring` task).
     *
     * @return Page<AwaitingScoring>
     */
    public function pageAwaitingScoring(int $companyId, int $assessorUserId, PageRequest $request): Page;

    /**
     * The invitations not started yet whose due time falls in the window and that were not reminded.
     *
     * @return list<Invitation>
     */
    public function dueForReminder(DateTimeImmutable $from, DateTimeImmutable $until): array;

    /**
     * The invitations not started whose due time has passed.
     *
     * @return list<Invitation>
     */
    public function dueForExpiry(DateTimeImmutable $now): array;

    /** Whether the test was ever sent: a published test that was sent is locked. */
    public function existsForTest(int $testId): bool;
}
