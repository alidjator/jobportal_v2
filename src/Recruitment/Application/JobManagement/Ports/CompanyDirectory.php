<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

/** Pemetaan user (role company) ke id perusahaan. Membaca tabel `companies` (milik context Company), read-only. */
interface CompanyDirectory
{
    public function idForUser(int $userId): ?int;
}
