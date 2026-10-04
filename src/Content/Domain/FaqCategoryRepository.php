<?php

namespace Src\Content\Domain;

interface FaqCategoryRepository
{
    public function findById(int $id): ?FaqCategory;

    /**
     * Ordered by sort order, then id.
     *
     * @return list<FaqCategory>
     */
    public function list(bool $onlyActive): array;

    public function hasFaqs(int $id): bool;

    public function save(FaqCategory $category): FaqCategory;

    public function delete(int $id): void;
}
