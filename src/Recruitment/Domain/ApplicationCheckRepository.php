<?php

namespace Src\Recruitment\Domain;

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
}
