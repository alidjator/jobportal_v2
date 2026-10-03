<?php

namespace Src\Recruitment\Domain\JobBoard;

/**
 * Pembacaan lowongan untuk sisi publik/kandidat. Semua baris berupa array asosiatif
 * (lihat `Infrastructure\JobBoard\Persistence\JobRows` untuk bentuk baris daftar).
 */
interface JobCatalog
{
    /**
     * Lowongan aktif (bukan menunggu persetujuan edit) sesuai filter.
     *
     * @return Page rows daftar lowongan
     */
    public function search(JobFilters $filters, ?int $candidateId, int $perPage, int $page): Page;

    /**
     * Detail lowongan berdasarkan slug, atau null bila tidak ada.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug, ?int $candidateId): ?array;

    /**
     * Lowongan terkait (kategori sama), maksimal 18.
     *
     * @return list<array<string, mixed>>
     */
    public function related(int $jobId, ?int $categoryId, ?int $candidateId): array;

    /** @return list<array<string, mixed>> */
    public function resumesOf(int $candidateId): array;
}
