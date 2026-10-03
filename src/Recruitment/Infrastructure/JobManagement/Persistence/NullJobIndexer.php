<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Src\Recruitment\Application\JobManagement\Ports\JobIndexer;

/**
 * Aplikasi lama memanggil Google Indexing API (paket google/apiclient + resources/credentials.json)
 * saat lowongan aktif disimpan. Paket itu belum ada di proyek ini, jadi adaptornya no-op;
 * ganti binding di JobManagementServiceProvider bila adaptor Google ditambahkan.
 */
class NullJobIndexer implements JobIndexer
{
    public function jobPublished(array $job): void {}
}
