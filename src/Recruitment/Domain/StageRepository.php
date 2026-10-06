<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

interface StageRepository
{
    public function companyHasStages(int $companyId): bool;

    /** @param  list<Stage>  $stages */
    public function addMany(array $stages, DateTimeImmutable $at): void;

    /**
     * Locks the company's stages until the transaction ends, so two changes of the same company run one after the other.
     */
    public function lockCompany(int $companyId): void;

    /**
     * The company's stages in their order.
     *
     * @return list<Stage>
     */
    public function forCompany(int $companyId): array;

    /**
     * @return int the id of the new stage
     *
     * @throws StateConflict `stage-name-taken`
     */
    public function add(Stage $stage, DateTimeImmutable $at): int;

    /**
     * @throws StateConflict `stage-name-taken`
     */
    public function rename(int $id, string $name, DateTimeImmutable $at): void;

    public function delete(int $id): void;

    /** @param  array<int, int>  $positions  the new position of each stage, by stage id */
    public function savePositions(array $positions): void;
}
