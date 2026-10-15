<?php

namespace Tests\Feature\ReferenceData;

use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

final class LocationTest extends FeatureTestCase
{
    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids['aceh'] = $this->row('reference_provinces', ['bps_code' => '11', 'name' => 'ACEH']);
        $this->ids['bali'] = $this->row('reference_provinces', ['bps_code' => '51', 'name' => 'BALI']);

        $this->ids['banda'] = $this->row('reference_cities', ['province_id' => $this->ids['aceh'], 'bps_code' => '1171', 'name' => 'BANDA ACEH', 'type' => 'city']);
        $this->ids['aceh-besar'] = $this->row('reference_cities', ['province_id' => $this->ids['aceh'], 'bps_code' => '1106', 'name' => 'ACEH BESAR', 'type' => 'regency']);
        $this->ids['denpasar'] = $this->row('reference_cities', ['province_id' => $this->ids['bali'], 'bps_code' => '5171', 'name' => 'DENPASAR', 'type' => 'city']);

        $this->ids['meuraxa'] = $this->row('reference_subdistricts', ['city_id' => $this->ids['banda'], 'bps_code' => '117101', 'name' => 'MEURAXA']);
        $this->ids['kuta'] = $this->row('reference_subdistricts', ['city_id' => $this->ids['denpasar'], 'bps_code' => '517101', 'name' => 'DENPASAR SELATAN']);

        $this->ids['deah'] = $this->row('reference_villages', ['subdistrict_id' => $this->ids['meuraxa'], 'bps_code' => '1171011001', 'name' => 'DEAH BARU']);
        $this->row('reference_villages', ['subdistrict_id' => $this->ids['meuraxa'], 'bps_code' => '1171011002', 'name' => 'ALUE DEAH TENGAH']);

        $this->row('reference_postal_codes', ['village_id' => $this->ids['deah'], 'code' => '23236'], false);
        $this->row('reference_postal_codes', ['village_id' => $this->ids['deah'], 'code' => '23231'], false);

    }

    /** @param  array<string, mixed>  $values */
    private function row(string $table, array $values, bool $timestamps = true): int
    {
        return (int) DB::table($table)->insertGetId([...$values, ...($timestamps ? ['created_at' => now(), 'updated_at' => now()] : [])]);
    }

    public function test_the_provinces_are_listed_by_name_without_a_filter(): void
    {
        $response = $this->getJson('/api/v1/provinces')->assertOk();

        $this->assertSame(['ACEH', 'BALI'], array_column($response->json('data'), 'name'));
        $this->assertSame(['11', '51'], array_column($response->json('data'), 'bps_code'));
        $this->assertArrayNotHasKey('links', $response->json());
    }

    public function test_cities_are_listed_per_province_with_their_type(): void
    {
        $response = $this->getJson("/api/v1/cities?filter[province_id]={$this->ids['aceh']}")->assertOk();

        $this->assertSame(['ACEH BESAR', 'BANDA ACEH'], array_column($response->json('data'), 'name'));
        $this->assertSame(['regency', 'city'], array_column($response->json('data'), 'type'));
        $this->assertSame(['1106', '1171'], array_column($response->json('data'), 'bps_code'));
    }

    public function test_every_level_below_the_provinces_needs_its_parent(): void
    {
        foreach (['cities', 'subdistricts', 'villages', 'postal-codes'] as $level) {
            $this->getJson("/api/v1/{$level}")
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:onesis:error:location-parent-required');
        }
    }

    public function test_subdistricts_villages_and_postal_codes_are_listed_per_parent(): void
    {
        $this->getJson("/api/v1/subdistricts?filter[city_id]={$this->ids['banda']}")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'MEURAXA');

        $villages = $this->getJson("/api/v1/villages?filter[subdistrict_id]={$this->ids['meuraxa']}")->assertOk();
        $this->assertSame(['ALUE DEAH TENGAH', 'DEAH BARU'], array_column($villages->json('data'), 'name'));

        $postal = $this->getJson("/api/v1/postal-codes?filter[village_id]={$this->ids['deah']}")->assertOk();
        $this->assertSame([['id' => $postal->json('data.0.id'), 'code' => '23231'], ['id' => $postal->json('data.1.id'), 'code' => '23236']], $postal->json('data'));
    }

    public function test_a_parent_without_children_gives_an_empty_list(): void
    {
        $this->getJson('/api/v1/cities?filter[province_id]=999999')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_only_the_parent_filter_of_the_level_is_accepted(): void
    {
        $this->getJson('/api/v1/provinces?filter[name]=ACEH')->assertStatus(422);
        $this->getJson("/api/v1/cities?filter[city_id]={$this->ids['banda']}")->assertStatus(422);
        $this->getJson('/api/v1/cities?filter[province_id]=abc')->assertStatus(422);
        $this->getJson('/api/v1/cities?filter[province_id]=0')->assertStatus(422);
    }

    public function test_location_responses_are_cacheable(): void
    {
        $response = $this->getJson('/api/v1/provinces')->assertOk();

        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
        $this->getJson('/api/v1/provinces', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
    }
}
