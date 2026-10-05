<?php

namespace Src\Company\Domain;

interface DocumentTypeRepository
{
    /** Every type, inactive ones included, ordered by `sort_order` then id. @return list<DocumentType> */
    public function all(): array;

    public function find(int $id): ?DocumentType;

    /** Locks the type row until the transaction ends, so two admins changing it at the same moment are applied one after the other. */
    public function lock(int $id): void;

    public function slugExists(string $slug): bool;

    /** Inserts a new type and returns it with its id. */
    public function create(DocumentType $type): DocumentType;

    /** Stores the name, flags and order of an existing type (the slug never changes). */
    public function save(DocumentType $type): void;
}
