<?php

namespace Src\Company\Domain;

interface ApplicationGroupRepository
{
    /** @return list<array<string,mixed>> */
    public function forCompany(int $companyId): array;

    /** @return array<string,mixed>|null */
    public function find(int $companyId, int $groupId): ?array;

    /** @return array<string,mixed> */
    public function create(int $companyId, string $name): array;

    /** @return array<string,mixed> */
    public function rename(int $groupId, string $name): array;

    /** Hapus grup; lamaran di dalamnya dipindah ke grup default (`is_deleteable = false`) milik perusahaan bila ada. */
    public function deleteMovingApplications(int $companyId, int $groupId): void;
}
