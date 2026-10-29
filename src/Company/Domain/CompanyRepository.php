<?php

namespace Src\Company\Domain;

interface CompanyRepository
{
    public function findById(int $id): ?Company;

    public function findBySlug(string $slug): ?Company;

    /** How many companies the installation holds, whatever their status. */
    public function count(): int;

    /** Whether a company other than `$exceptId` already uses the slug. */
    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** Locks the company row until the transaction ends, so changes to its team and invitations run one at a time. */
    public function lock(int $companyId): void;

    /** Inserts a new company, or stores the profile, slug, status and images of an existing one, and returns it with its id. Never writes the verification badge. */
    public function save(Company $company): Company;

    /**
     * The email domains a company admin may invite to, or null when the company sets no limit (the default).
     *
     * @return ?list<string>
     */
    public function invitationEmailDomains(int $companyId): ?array;

    /** @param  ?list<string>  $domains  lower case, null for no limit */
    public function setInvitationEmailDomains(int $companyId, ?array $domains): void;
}
