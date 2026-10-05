<?php

namespace Src\Company\Domain;

interface CompanyRepository
{
    public function findById(int $id): ?Company;

    public function findBySlug(string $slug): ?Company;

    /** Whether a company other than `$exceptId` already uses the slug. */
    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** Locks the company row until the transaction ends, so changes to its team and invitations run one at a time. */
    public function lock(int $companyId): void;

    /** Inserts a new company, or stores the profile, slug, status and images of an existing one, and returns it with its id. Never writes the verification badge. */
    public function save(Company $company): Company;
}
