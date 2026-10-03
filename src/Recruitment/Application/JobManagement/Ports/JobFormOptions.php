<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

/** Data master untuk form lowongan (membaca tabel referensi context ReferenceData, read-only). */
interface JobFormOptions
{
    /** @return array<string, mixed> */
    public function forCreate(): array;

    /** @return array<string, mixed> */
    public function forEdit(): array;
}
