<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

/** Pemberitahuan ke mesin pencari (Google Indexing) saat lowongan aktif disimpan. */
interface JobIndexer
{
    /** @param  array<string, mixed>  $job */
    public function jobPublished(array $job): void;
}
