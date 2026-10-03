<?php

namespace Src\Company\Domain;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookmarkRepository
{
    /** @return list<array<string,mixed>> */
    public function categories(int $companyId): array;

    /** @return array<string,mixed>|null */
    public function category(int $companyId, int $categoryId): ?array;

    /** @return array<string,mixed> */
    public function createCategory(int $companyId, string $name): array;

    /** @return array<string,mixed> */
    public function updateCategory(int $categoryId, string $name): array;

    public function deleteCategory(int $categoryId): void;

    public function categoryExists(int $companyId, int $categoryId): bool;

    public function paginateCandidates(int $companyId, ?int $categoryId, int $perPage): LengthAwarePaginator;

    /** @return array{id:int,name:string,username:string,shortlisted_alert:bool}|null kandidat berdasarkan username */
    public function findCandidateByUsername(string $username): ?array;

    public function isBookmarked(int $companyId, int $candidateId): bool;

    public function addBookmark(int $companyId, int $candidateId, ?int $categoryId): void;

    public function removeBookmark(int $companyId, int $candidateId): void;

    /**
     * Sisa kuota lihat CV; `null` bila perusahaan tidak punya plan atau kuotanya tak terbatas.
     */
    public function remainingCvViews(int $companyId): ?int;

    public function consumeCvView(int $companyId): void;
}
