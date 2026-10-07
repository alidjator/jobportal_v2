<?php

namespace Tests\Feature\LegacyImport;

use Src\LegacyImport\Application\LocationLookup;
use Src\LegacyImport\Application\ResolvedLocation;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class LocationLookupTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    /** @var array{province_id: int, city_id: int, subdistrict_id: int, village_id: int, postal_code_id: int} */
    private array $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chain = $this->locationChain();
    }

    private function resolve(?string ...$codes): ResolvedLocation
    {
        return $this->app->make(LocationLookup::class)->resolve(...$codes);
    }

    private function ids(ResolvedLocation $location): array
    {
        return [$location->provinceId, $location->cityId, $location->subdistrictId, $location->villageId, $location->postalCodeId];
    }

    public function test_a_valid_chain_resolves_every_level(): void
    {
        $location = $this->resolve('32', '3273', '327301', '3273011001', '40153');

        $this->assertSame(array_values($this->chain), $this->ids($location));
        $this->assertFalse($location->isEmpty());
    }

    public function test_the_chain_is_cut_at_the_first_level_that_does_not_belong_to_the_one_above(): void
    {
        $this->assertSame([$this->chain['province_id'], null, null, null, null], $this->ids($this->resolve('32', '9999', '327301', '3273011001', '40153')));
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], null, null, null], $this->ids($this->resolve('32', '3273', '999999', '3273011001', '40153')));
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], $this->chain['subdistrict_id'], null, null], $this->ids($this->resolve('32', '3273', '327301', '0000000000', '40153')));
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], $this->chain['subdistrict_id'], $this->chain['village_id'], null], $this->ids($this->resolve('32', '3273', '327301', '3273011001', '99999')));
    }

    public function test_a_city_of_another_province_is_not_accepted(): void
    {
        $this->assertSame([$this->chain['province_id'], null, null, null, null], $this->ids($this->resolve('32', '3173', null, null, null)));
    }

    public function test_an_unknown_or_missing_first_level_gives_an_empty_location(): void
    {
        $this->assertTrue($this->resolve('99', '3273', '327301', '3273011001', '40153')->isEmpty());
        $this->assertTrue($this->resolve(null, '3273', null, null, null)->isEmpty());
        $this->assertTrue($this->resolve('0', null, null, null, null)->isEmpty());
        $this->assertTrue($this->resolve('  ', null, null, null, null)->isEmpty());
    }

    public function test_a_level_below_a_missing_one_is_ignored(): void
    {
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], null, null, null], $this->ids($this->resolve('32', '3273', null, '3273011001', '40153')));
    }
}
