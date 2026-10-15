<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** The name change requests of companies. Callers lock the company row (`CompanyRepository::lock`) before they check for a pending request and then insert. */
interface NameChangeRequestRepository
{
    public function find(int $id): ?NameChangeRequest;

    public function pendingOf(int $companyId): ?NameChangeRequest;

    public function create(NameChangeRequest $request): NameChangeRequest;

    public function save(NameChangeRequest $request): void;

    /** @return list<NameChangeRequest> the newest first */
    public function ofCompany(int $companyId, int $limit): array;

    /**
     * Every company's requests, the pending ones first (the oldest first, they wait longest), then the others newest first.
     *
     * @return Page<NameChangeRequest>
     */
    public function page(?NameChangeStatus $status, PageRequest $request): Page;

    /**
     * Requests decided before the time that still hold a document: the oldest `$limit`.
     *
     * @return list<NameChangeRequest>
     */
    public function withDocumentDecidedBefore(DateTimeImmutable $before, int $limit): array;

    public function clearDocument(int $id): void;
}
