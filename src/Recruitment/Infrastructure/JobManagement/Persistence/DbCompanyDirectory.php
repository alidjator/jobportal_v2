<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;

class DbCompanyDirectory implements CompanyDirectory
{
    public function idForUser(int $userId): ?int
    {
        // Baca-saja tabel `companies` (context Company): hanya untuk memetakan user -> perusahaan.
        $id = DB::table('companies')->where('user_id', $userId)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
