<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

/** Pengaturan situs yang dipakai alur lowongan (tabel `settings`, baris pertama). */
interface PostingSettings
{
    public function jobAutoApproved(): bool;

    public function editedJobAutoApproved(): bool;

    public function deadlineExpirationLimitDays(): int;

    public function featuredJobDays(): int;

    public function highlightJobDays(): int;

    /** Nama negara aplikasi (`settings.app_country`), atau string kosong. */
    public function appCountryName(): string;
}
