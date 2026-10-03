<?php

namespace Src\Recruitment\Domain\JobManagement;

/**
 * Penyimpanan lowongan sisi perusahaan. Lowongan dipertukarkan sebagai array
 * berbentuk JSON API lama (kolom `jobs` + atribut turunan).
 */
interface JobRepository
{
    /** @return array<string, mixed>|null */
    public function findOwnedBySlug(int $companyId, string $slug): ?array;

    /** @return array<string, mixed>|null salinan hasil edit (menunggu persetujuan) dari lowongan induk */
    public function findPendingEdit(int $companyId, int $parentJobId): ?array;

    public function ownsJob(int $companyId, int $jobId): bool;

    /**
     * @param  array<string, mixed>  $attributes  kolom `jobs` (tanpa slug; dibuat dari title)
     * @return array<string, mixed>
     */
    public function create(array $attributes): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(int $jobId, array $attributes): array;

    /**
     * Salin lowongan (slug baru, view 0) lalu timpa dengan $overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function replicate(int $jobId, array $overrides): array;

    /**
     * @return array<string, mixed> paginator (bentuk JSON `LengthAwarePaginator`)
     */
    public function paginateForCompany(int $companyId, string $status, int $perPage): array;

    /** @param  list<string>  $names  nama tag; yang belum ada dibuat (semua bahasa) */
    public function attachTags(int $jobId, array $names, bool $replace): void;

    /** @return array{tags: list<array<string, mixed>>, benefits: list<array<string, mixed>>} */
    public function tagsAndBenefits(int $jobId): array;
}
