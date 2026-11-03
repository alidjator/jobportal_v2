<?php

namespace Src\Workforce\Domain;

interface RequisitionDocumentRepository
{
    public function create(RequisitionDocument $document): RequisitionDocument;

    public function find(int $requisitionId, int $id): ?RequisitionDocument;

    /** @return list<RequisitionDocument> oldest first */
    public function of(int $requisitionId): array;

    public function count(int $requisitionId): int;

    public function delete(int $requisitionId, int $id): void;
}
