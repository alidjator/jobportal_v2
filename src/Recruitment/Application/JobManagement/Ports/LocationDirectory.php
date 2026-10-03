<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

/** Nama wilayah berdasarkan kode BPS (tabel `cities`, `subdistricts`). */
interface LocationDirectory
{
    public function cityName(int $bpsCode): string;

    public function subdistrictName(?int $bpsCode): string;
}
