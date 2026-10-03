<?php

namespace Src\Recruitment\Domain\JobBoard;

interface HomeCatalog
{
    /**
     * Data beranda mentah: states, featured_jobs (baris daftar), popular roles/categories,
     * top companies, top categories, testimonials.
     *
     * @return array<string, mixed>
     */
    public function home(?int $candidateId): array;
}
