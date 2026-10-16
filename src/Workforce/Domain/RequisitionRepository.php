<?php

namespace Src\Workforce\Domain;

interface RequisitionRepository
{
    /** `$lock` holds the row until the transaction ends. */
    public function find(int $id, bool $lock = false): ?Requisition;

    public function create(Requisition $requisition): Requisition;

    /** Stores every field of the requisition that can change. */
    public function save(Requisition $requisition): void;

    /**
     * The requisitions of the tenant, or of these legal entities, newest first.
     *
     * @param  ?list<int>  $legalEntityIds  null for no limit (the tenant side filters by company)
     * @return list<Requisition>
     */
    public function search(?int $companyId, ?array $legalEntityIds, ?RequisitionStatus $status, ?RequisitionType $type, ?int $positionId): array;

    /**
     * The hires the `planned` requisitions of the position already use in the fiscal year (the ones submitted or approved, and the extra hires of an amendment that waits for a decision).
     */
    public function usedHires(int $legalEntityId, int $positionId, int $fiscalYear, ?int $exceptRequisitionId = null): int;

    /**
     * The hires `usedHires` counts for every position that has a `planned` requisition in the fiscal year, keyed by position id.
     *
     * @return array<int, int>
     */
    public function usedHiresByPosition(int $legalEntityId, int $fiscalYear): array;

    public function findAmendment(int $id, bool $lock = false): ?RequisitionAmendment;

    public function createAmendment(RequisitionAmendment $amendment): RequisitionAmendment;

    public function saveAmendment(RequisitionAmendment $amendment): void;

    /** @return list<RequisitionAmendment> oldest first */
    public function amendmentsOf(int $requisitionId): array;

    public function pendingAmendment(int $requisitionId): ?RequisitionAmendment;
}
