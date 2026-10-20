<?php

namespace Src\TalentPool\Domain;

use DateTimeImmutable;
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
     * Every entry of the company (at most 1,000), the most recently saved first, optionally of one category and one source.
     *
     * @return list<Entry>
     */
    public function allOfCompany(int $companyId, ?int $categoryId = null, ?EntrySource $source = null): array;

    /**
     * The most recently saved entries of the company, one page, optionally of one category and one source.
     *
     * @return Page<Entry>
     */
    public function pageOfCompany(int $companyId, ?int $categoryId, ?EntrySource $source, PageRequest $request): Page;

    /**
     * The entries of the company with these ids; an id that is not the company's is missing.
     *
     * @param  list<int>  $ids
     * @return list<Entry>
     */
    public function findMany(int $companyId, array $ids): array;

    /** The company's entry for the candidate, or null. */
    public function findOfCandidate(int $companyId, int $candidateUserId): ?Entry;

    /**
     * How many entries each category holds.
     *
     * @return array<int, int> by category id
     */
    public function countsByCategory(int $companyId): array;

    /**
     * The entries that hold the candidate, one per company, oldest first.
     *
     * @return list<Entry>
     */
    public function ofCandidate(int $candidateUserId): array;

    public function deleteOfCandidate(int $candidateUserId): void;

    /**
     * Entries saved from a consenting application (they rest on the consent), in id order, starting after `$afterId`.
     *
     * @return list<Entry>
     */
    public function restingOnConsentAfter(int $afterId, int $limit): array;

    /**
     * Entries carried over from karir.pkp (the `legacy` source) that were last touched at or before `$untouchedSince` (the import, or the company's last change), in id order, starting after `$afterId`.
     *
     * @return list<Entry>
     */
    public function restingOnLegacyAfter(int $afterId, int $limit, DateTimeImmutable $untouchedSince): array;

    /** Deletes the entries of the `legacy` source that hold the candidate and returns how many were deleted. */
    public function deleteLegacyOfCandidate(int $candidateUserId): int;

    /**
     * Deletes these entries and returns how many were deleted.
     *
     * @param  list<int>  $ids
     */
    public function removeMany(array $ids): int;

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
