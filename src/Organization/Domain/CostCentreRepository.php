<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

interface CostCentreRepository
{
    public function find(int $id): ?CostCentre;

    public function create(CostCentre $centre): CostCentre;

    public function save(CostCentre $centre): void;

    /** @return list<CostCentre> by code */
    public function ofEntity(int $legalEntityId): array;

    public function codeTaken(int $legalEntityId, string $code): bool;

    /** Whether an active unit has the cost centre as its default now or later. */
    public function inUse(int $id, DateTimeImmutable $today): bool;
}
