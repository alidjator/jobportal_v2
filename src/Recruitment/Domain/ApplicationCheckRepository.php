<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

interface ApplicationCheckRepository
{
    /** @throws StateConflict `check-already-open` when the application has an open check of the type */
    public function add(ApplicationCheck $check): ApplicationCheck;

    public function save(ApplicationCheck $check): void;

    public function find(int $applicationId, int $id, bool $forUpdate = false): ?ApplicationCheck;

    /**
     * @return list<ApplicationCheck> the oldest first
     */
    public function ofApplication(int $applicationId): array;

    /**
     * The checks whose retention ended: they still hold what the retention deletes (a medical check its outcome, note or file, any other check its file) and their application
     * entered a final stage (`rejected`, `withdrawn` or `hired`) at or before `$finalBefore`.
     *
     * @return list<ApplicationCheck> the oldest first, at most `$limit`
     */
    public function dueForPurge(DateTimeImmutable $finalBefore, int $limit): array;
}
