<?php

namespace Src\Content\Domain;

interface FaqRepository
{
    public function findById(int $id): ?Faq;

    /**
     * Ordered by sort order, then id.
     *
     * @return list<Faq>
     */
    public function list(?int $categoryId, bool $onlyActive): array;

    public function save(Faq $faq): Faq;

    public function delete(int $id): void;
}
