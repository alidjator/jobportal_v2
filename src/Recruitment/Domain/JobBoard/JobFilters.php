<?php

namespace Src\Recruitment\Domain\JobBoard;

/**
 * Kriteria pencarian lowongan publik (query string `/jobs`). Nilai kosong berarti filter tidak dipakai.
 */
final class JobFilters
{
    public function __construct(
        public readonly ?string $keyword = null,
        public readonly ?string $category = null,
        public readonly ?string $jobRole = null,
        public readonly ?string $jobType = null,
        public readonly ?string $experience = null,
        public readonly ?string $education = null,
        public readonly ?string $company = null,
        public readonly ?string $tag = null,
        public readonly ?string $location = null,
        public readonly ?float $lat = null,
        public readonly ?float $long = null,
        public readonly float $radius = 50,
        public readonly ?float $priceMin = null,
        public readonly ?float $priceMax = null,
        public readonly bool $isRemote = false,
        public readonly ?string $sortBy = null,
    ) {}
}
