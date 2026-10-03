<?php

namespace Src\Content\Domain;

interface AboutRepository
{
    /** @return array<int, mixed> */
    public function testimonials(): array;

    /** Jumlah lowongan terbuka (mengikuti negara terpilih / pengaturan single-country). */
    public function liveJobs(?int $countryId): int;

    public function companyCount(): int;

    public function candidateCount(): int;
}
