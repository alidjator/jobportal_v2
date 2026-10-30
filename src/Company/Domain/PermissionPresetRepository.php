<?php

namespace Src\Company\Domain;

use Src\Shared\Domain\Exceptions\StateConflict;

interface PermissionPresetRepository
{
    /** @throws StateConflict `permission-preset-name-taken` when the company has a preset of that name (any case) */
    public function add(PermissionPreset $preset): PermissionPreset;

    /** @throws StateConflict `permission-preset-name-taken` */
    public function save(PermissionPreset $preset): void;

    public function find(int $companyId, int $id, bool $forUpdate = false): ?PermissionPreset;

    /** @return list<PermissionPreset> by name */
    public function ofCompany(int $companyId): array;

    public function countOfCompany(int $companyId): int;

    public function delete(int $companyId, int $id): void;
}
