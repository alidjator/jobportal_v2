<?php

namespace Src\Content\Domain;

use Src\Shared\Domain\Exceptions\StateConflict;

interface LegalDocumentRepository
{
    /** The next version number of the type: the highest existing version plus one, or 1. */
    public function nextVersion(LegalDocumentType $type): int;

    public function findById(int $id): ?LegalDocument;

    /** The active version of the type, or null when none was published yet. */
    public function findPublished(LegalDocumentType $type): ?LegalDocument;

    public function findVersion(LegalDocumentType $type, int $version): ?LegalDocument;

    /**
     * Newest version first.
     *
     * @return list<LegalDocument>
     */
    public function list(?LegalDocumentType $type): array;

    /**
     * Locks every document of the type until the transaction ends, so two admins cannot publish two versions at the same time.
     * Must be called inside a transaction.
     */
    public function lockType(LegalDocumentType $type): void;

    /** @throws StateConflict `legal-document-version-taken` when another admin created the same version first */
    public function save(LegalDocument $document): LegalDocument;

    public function delete(int $id): void;
}
