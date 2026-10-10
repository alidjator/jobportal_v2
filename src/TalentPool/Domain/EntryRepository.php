<?php

namespace Src\TalentPool\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

interface EntryRepository
{
    /** @throws StateConflict `already-in-talent-pool` */
    public function add(Entry $entry): Entry;

    /** The entry, only when it belongs to the company. */
    public function find(int $companyId, int $id): ?Entry;

    public function exists(int $companyId, int $candidateUserId): bool;

    public function count(int $companyId): int;

    public function changeCategory(int $companyId, int $id, ?int $categoryId): void;

    public function changeNote(int $companyId, int $id, ?string $note): void;

    public function remove(int $companyId, int $id): void;

    /**
     * @return Page<Entry> the most recently saved first
     */
    public function ofCompany(int $companyId, ?int $categoryId, PageRequest $request): Page;

    /**
     * How many entries each category holds.
     *
     * @return array<int, int> by category id
     */
    public function countsByCategory(int $companyId): array;

    public function deleteOfCandidate(int $candidateUserId): void;

    /**
     * Entries that carry a note, in id order, starting after `$afterId`.
     *
     * @return list<Entry>
     */
    public function withNotesAfter(int $afterId, int $limit): array;

    /**
     * Clears the note of these entries (their candidates stay saved) and returns how many were cleared.
     *
     * @param  list<int>  $ids
     */
    public function clearNotes(array $ids): int;
}
