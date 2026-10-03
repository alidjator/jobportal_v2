<?php

namespace Tests\Feature\Database\Seeders\Reference;

use Database\Seeders\Reference\LocationSeeder;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

/**
 * The seeder loads about 175,000 rows, so the checks share one test that runs it twice
 * (the second run proves it adds nothing) instead of running it once per assertion.
 */
final class LocationSeederTest extends FeatureTestCase
{
    public function test_it_seeds_the_whole_hierarchy_once_and_keeps_it_consistent(): void
    {
        $this->seed(LocationSeeder::class);
        $this->seed(LocationSeeder::class);

        $this->assertRowCounts();
        $this->assertCitiesKeepTheirKindInType();
        $this->assertRowsAreLinkedByBpsCode();
        $this->assertCodeLengthsAndNoCoordinates();
        $this->assertTheVillageWithoutPostalCode();
    }

    private function assertRowCounts(): void
    {
        $this->assertSame(38, DB::table('reference_provinces')->count());
        $this->assertSame(514, DB::table('reference_cities')->count());
        $this->assertSame(7285, DB::table('reference_subdistricts')->count());
        $this->assertSame(83762, DB::table('reference_villages')->count());
        $this->assertSame(83761, DB::table('reference_postal_codes')->count());
    }

    private function assertCitiesKeepTheirKindInType(): void
    {
        $this->assertSame(98, DB::table('reference_cities')->where('type', 'city')->count());
        $this->assertSame(416, DB::table('reference_cities')->where('type', 'regency')->count());
        $this->assertSame(0, DB::table('reference_cities')->where('name', 'like', 'KABUPATEN %')->orWhere('name', 'like', 'KOTA %')->orWhere('name', 'like', 'KAB %')->count());

        $regency = DB::table('reference_cities')->where('bps_code', '5302')->first();
        $this->assertSame('TIMOR TENGAH SELATAN', $regency->name);
        $this->assertSame('regency', $regency->type);
    }

    private function assertRowsAreLinkedByBpsCode(): void
    {
        $village = DB::table('reference_villages as v')
            ->join('reference_subdistricts as d', 'd.id', '=', 'v.subdistrict_id')
            ->join('reference_cities as c', 'c.id', '=', 'd.city_id')
            ->join('reference_provinces as p', 'p.id', '=', 'c.province_id')
            ->where('v.bps_code', '1101012002')
            ->first(['v.name as village', 'd.bps_code as subdistrict', 'c.bps_code as city', 'p.bps_code as province', 'p.name as province_name']);

        $this->assertSame('UJONG MANGKI', $village->village);
        $this->assertSame(['110101', '1101', '11', 'ACEH'], [$village->subdistrict, $village->city, $village->province, $village->province_name]);
    }

    private function assertCodeLengthsAndNoCoordinates(): void
    {
        foreach (['reference_provinces' => 2, 'reference_cities' => 4, 'reference_subdistricts' => 6, 'reference_villages' => 10] as $table => $length) {
            $this->assertSame(0, DB::table($table)->whereRaw('CHAR_LENGTH(bps_code) <> ?', [$length])->count(), $table);
            $this->assertSame(0, DB::table($table)->whereNotNull('latitude')->orWhereNotNull('longitude')->count(), $table);
        }

        $this->assertSame(0, DB::table('reference_postal_codes')->whereRaw('code NOT REGEXP ?', ['^[0-9]{5}$'])->count());
    }

    private function assertTheVillageWithoutPostalCode(): void
    {
        $villageId = DB::table('reference_villages')->where('bps_code', '1101012001')->value('id');

        $this->assertNotNull($villageId);
        $this->assertSame(0, DB::table('reference_postal_codes')->where('village_id', $villageId)->count());
        $this->assertSame(1, DB::table('reference_villages as v')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('reference_postal_codes as z')->whereColumn('z.village_id', 'v.id'))->count());
    }
}
