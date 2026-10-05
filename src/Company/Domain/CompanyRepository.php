<?php

namespace Src\Company\Domain;

interface CompanyRepository
{
    public function findById(int $id): ?Company;

    public function slugExists(string $slug): bool;

    /** Locks the company row until the transaction ends, so changes to its team and invitations run one at a time. */
    public function lock(int $companyId): void;

    /** Inserts a new company and returns it with its id. */
    public function save(Company $company): Company;
}
