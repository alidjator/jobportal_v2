<?php

namespace Src\CandidateProfile\Domain;

/** Pembacaan publik daftar & detail kandidat. */
interface CandidateDirectory
{
    /**
     * @param  array<string,mixed>  $filters  keyword,status,profession,experience,education,gender,language,skills,location,lat,long,sortby
     * @param  int|null  $companyUserId  user company yang sedang melihat (untuk flag `bookmarked`)
     * @return list<array<string,mixed>>
     */
    public function search(array $filters, int $perPage, ?int $companyUserId): array;

    /** @return array<string,mixed>|null */
    public function detail(string $username): ?array;
}
