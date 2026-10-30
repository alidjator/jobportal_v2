<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface PendingRejectionRepository
{
    /** Stores the pending rejection; false when the application already has one (an application waits for one rejection only). */
    public function add(PendingRejection $pending): bool;

    public function findByApplication(int $applicationId): ?PendingRejection;

    /**
     * The pending rejection of each of these applications, keyed by application id; an application without one is missing.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, PendingRejection>
     */
    public function forApplications(array $applicationIds): array;

    /** Whether the application waits for a rejection. */
    public function exists(int $applicationId): bool;

    /** Removes the pending rejection of the application when it is not due yet; false when there is none or it is already due. */
    public function undo(int $applicationId, DateTimeImmutable $now): bool;

    /**
     * Removes every pending rejection of a batch that is not due yet, for the applications of the company.
     *
     * @return list<int> the applications whose rejection was undone
     */
    public function undoBatch(string $batchId, int $companyId, DateTimeImmutable $now): array;

    /**
     * The pending rejections that are due, oldest first.
     *
     * @return list<PendingRejection>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /** Takes a due row for committing: removes it and says whether this call did (an undo may have been first). */
    public function claim(int $id): bool;
}
