<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\StateConflict;

interface CheckTypeRepository
{
    /** @throws StateConflict `check-type-name-taken` */
    public function add(CheckType $type): CheckType;

    /** @throws StateConflict `check-type-name-taken` */
    public function save(CheckType $type): void;

    public function find(int $companyId, int $id, bool $forUpdate = false): ?CheckType;

    /**
     * @return list<CheckType> by name
     */
    public function ofCompany(int $companyId): array;

    /**
     * The types of these ids, keyed by id, of any company (the caller already checked the checks belong to one application).
     *
     * @param  list<int>  $ids
     * @return array<int, CheckType>
     */
    public function byIds(array $ids): array;
}
