<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\JobManagement\Ports\LocationDirectory;

class DbLocationDirectory implements LocationDirectory
{
    public function cityName(int $bpsCode): string
    {
        return (string) DB::table('cities')->where('bps_code', $bpsCode)->value('name');
    }

    public function subdistrictName(?int $bpsCode): string
    {
        return $bpsCode === null ? '' : (string) DB::table('subdistricts')->where('bps_code', $bpsCode)->value('name');
    }
}
