<?php

namespace Src\Company\Domain;

interface CompanyRepository
{
    public function findById(int $id): ?Company;

    public function slugExists(string $slug): bool;

    /** Inserts a new company and returns it with its id. */
    public function save(Company $company): Company;
}
