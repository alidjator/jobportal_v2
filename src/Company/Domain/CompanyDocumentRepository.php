<?php

namespace Src\Company\Domain;

interface CompanyDocumentRepository
{
    public function find(int $companyId, int $documentTypeId): ?CompanyDocument;

    /** Every document of the company, in no particular order. @return list<CompanyDocument> */
    public function ofCompany(int $companyId): array;

    /** Inserts the document and returns it with its id. */
    public function create(CompanyDocument $document): CompanyDocument;

    /** Stores the file, status, review and timestamps of an existing document. */
    public function save(CompanyDocument $document): void;

    public function delete(int $id): void;
}
