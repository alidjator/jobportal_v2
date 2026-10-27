<?php

namespace Src\Assessment\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface LicenceSubmissionRepository
{
    /** Inserts a new submission or updates the decision of an existing one, and returns it (with its id). */
    public function save(LicenceSubmission $submission): LicenceSubmission;

    public function find(int $id, bool $forUpdate = false): ?LicenceSubmission;

    /**
     * The psychologist's submissions, newest first.
     *
     * @return list<LicenceSubmission>
     */
    public function ofPsychologist(int $psychologistId): array;

    public function hasPending(int $psychologistId, LicenceKind $kind): bool;

    /**
     * The admin's queue: pending submissions first (oldest first, so none is forgotten), then the decided ones, newest decision first.
     *
     * @return Page<LicenceQueueRow>
     */
    public function queue(?LicenceStatus $status, PageRequest $request): Page;

    /** How many submissions wait for a decision. */
    public function pendingCount(): int;
}
