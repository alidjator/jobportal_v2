<?php

namespace Src\Company\Domain;

interface CompanyLocationRepository
{
    /** The location of this company, or null when it does not exist or belongs to another company. */
    public function find(int $companyId, int $locationId): ?CompanyLocation;

    /** The main location of the company, or null for a company that has none stored (it should always have one). */
    public function main(int $companyId): ?CompanyLocation;

    /**
     * The locations of the company: active ones first, then by name.
     *
     * @return list<CompanyLocation>
     */
    public function forCompany(int $companyId, ?LocationStatus $status = null): array;

    public function count(int $companyId): int;

    /** Whether the company already has a location with this name, ignoring case. */
    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool;

    /** Inserts a new location and returns it with its id. @throws \Src\Shared\Domain\Exceptions\BusinessRuleViolation `location-name-taken` when the unique index refuses the name */
    public function add(CompanyLocation $location): CompanyLocation;

    /** Stores the changed fields of an existing location. @throws \Src\Shared\Domain\Exceptions\BusinessRuleViolation `location-name-taken` */
    public function save(CompanyLocation $location): void;

    public function delete(int $companyId, int $locationId): void;

    /** Moves the main mark to another location of the company (the old main mark is cleared first, so the one-main index holds). */
    public function moveMain(int $companyId, int $locationId): void;
}
