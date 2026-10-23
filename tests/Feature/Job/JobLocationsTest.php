<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Job\Application\JobSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/company-locations.md` "Coded: #20": a job is posted for one or several work locations of its company. The first one is the main one and fills the address of the job;
 * search, matching and alerts see all of them.
 */
final class JobLocationsTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private int $bandungId;

    private int $jakartaId;

    /** @var array<string, int> */
    private array $bandung;

    /** @var array<string, int> */
    private array $jakarta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();

        $this->bandung = $this->locationChain();
        $province = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']);
        $this->jakarta = ['province_id' => $province, 'city_id' => (int) DB::table('reference_cities')->insertGetId(['province_id' => $province, 'bps_code' => '3171', 'name' => 'JAKARTA SELATAN', 'type' => 'city'])];

        $this->bandungId = (int) $this->api('POST', '/company/locations', ['name' => 'Cabang Bandung', 'type' => 'branch', ...$this->bandung, 'address_line' => 'Jl. Setiabudi 1'])->assertCreated()->json('data.id');
        $this->jakartaId = (int) $this->api('POST', '/company/locations', ['name' => 'Kantor Jakarta', 'type' => 'site', ...$this->jakarta, 'address_line' => 'Jl. Sudirman 5'])->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** A complete job body whose place is the given company locations instead of an address of its own. @param  list<int>  $locationIds @param  array<string, mixed>  $override @return array<string, mixed> */
    private function body(array $locationIds, array $override = []): array
    {
        $body = $this->fullJobBody('Backend Developer');
        unset($body['province_id'], $body['city_id'], $body['subdistrict_id'], $body['village_id'], $body['postal_code_id'], $body['address_line']);

        return [...$body, 'location_ids' => $locationIds, ...$override];
    }

    private function create(array $locationIds, array $override = []): int
    {
        return (int) $this->api('POST', '/company/jobs', $this->body($locationIds, $override), headers: ['Accept-Language' => 'en'])->assertCreated()->json('data.id');
    }

    private function publicJob(int $id): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->getJson("/api/v1/jobs/{$id}");
    }

    /** @return list<string> */
    private function search(string $query): array
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return array_column($this->getJson('/api/v1/jobs?'.$query)->assertOk()->json('data'), 'title');
    }

    // ----- saving -----

    public function test_a_job_is_saved_with_its_locations_and_the_first_one_fills_its_address(): void
    {
        $data = $this->api('POST', '/company/jobs', $this->body([$this->bandungId, $this->jakartaId]), headers: ['Accept-Language' => 'en'])->assertCreated()->json('data');

        $this->assertSame([$this->bandungId, $this->jakartaId], $data['location_ids']);
        $this->assertSame([$this->bandung['province_id'], $this->bandung['city_id'], $this->bandung['postal_code_id'], 'Jl. Setiabudi 1'], [$data['province_id'], $data['city_id'], $data['postal_code_id'], $data['address_line']]);
        $this->assertSame(
            ['id' => $this->bandungId, 'name' => 'Cabang Bandung', 'type' => 'branch', 'province' => 'JAWA BARAT', 'city' => 'BANDUNG', 'subdistrict' => 'SUKASARI', 'village' => 'GEGERKALONG', 'postal_code' => '40153', 'address_line' => 'Jl. Setiabudi 1'],
            $data['locations'][0],
        );
        $this->assertSame([$this->jakartaId, 'Kantor Jakarta', 'site', 'DKI JAKARTA', 'JAKARTA SELATAN'], [$data['locations'][1]['id'], $data['locations'][1]['name'], $data['locations'][1]['type'], $data['locations'][1]['province'], $data['locations'][1]['city']]);
        $this->assertSame([[$this->bandungId, 0], [$this->jakartaId, 1]], DB::table('job_job_locations')->where('job_id', $data['id'])->orderBy('position')->get()->map(fn (object $row): array => [(int) $row->location_id, (int) $row->position])->all());
    }

    public function test_the_order_decides_which_location_fills_the_address_and_an_empty_list_keeps_the_address(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);

        $moved = $this->api('PATCH', "/company/jobs/{$id}", ['location_ids' => [$this->jakartaId, $this->bandungId]])->assertOk()->json('data');
        $this->assertSame([[$this->jakartaId, $this->bandungId], $this->jakarta['province_id'], 'Jl. Sudirman 5'], [$moved['location_ids'], $moved['province_id'], $moved['address_line']]);

        $cleared = $this->api('PATCH', "/company/jobs/{$id}", ['location_ids' => []])->assertOk()->json('data');
        $this->assertSame([[], [], $this->jakarta['province_id'], 'Jl. Sudirman 5'], [$cleared['location_ids'], $cleared['locations'], $cleared['province_id'], $cleared['address_line']]);
        $this->assertSame(0, DB::table('job_job_locations')->where('job_id', $id)->count());
    }

    public function test_a_job_with_locations_cannot_also_carry_an_address_of_its_own(): void
    {
        $this->api('POST', '/company/jobs', [...$this->body([$this->bandungId]), 'city_id' => $this->bandung['city_id']])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-job')->assertJsonStructure(['errors' => ['location_ids']]);
        $this->assertSame(0, DB::table('job_jobs')->count());

        $id = $this->create([$this->bandungId]);
        $this->api('PATCH', "/company/jobs/{$id}", ['location_ids' => [$this->jakartaId], 'address_line' => 'Elsewhere'])->assertStatus(422)->assertJsonStructure(['errors' => ['location_ids']]);
        $this->assertSame([$this->bandungId], DB::table('job_job_locations')->where('job_id', $id)->pluck('location_id')->map(fn ($value): int => (int) $value)->all());
    }

    public function test_only_an_active_location_of_the_company_can_be_chosen(): void
    {
        $other = (int) DB::table('company_locations')->where('company_id', $this->otherCompanyId)->value('id');
        $inactive = (int) $this->api('POST', '/company/locations', ['name' => 'Closed site', 'type' => 'site'])->assertCreated()->json('data.id');
        $this->api('POST', "/company/locations/{$inactive}/deactivate")->assertOk();

        foreach ([[$other], [999999], [$inactive], [$this->bandungId, $other]] as $ids) {
            $this->api('POST', '/company/jobs', $this->body($ids))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-job')->assertJsonStructure(['errors' => ['location_ids']]);
        }

        $this->assertSame(0, DB::table('job_jobs')->count());
    }

    public function test_the_shape_of_the_list_is_checked(): void
    {
        $this->api('POST', '/company/jobs', $this->body(range(1, 11)))->assertStatus(422)->assertJsonValidationErrors(['location_ids']);
        $this->api('POST', '/company/jobs', $this->body([$this->bandungId, $this->bandungId]))->assertStatus(422)->assertJsonValidationErrors(['location_ids.0']);
        $this->api('POST', '/company/jobs', $this->body([0]))->assertStatus(422)->assertJsonValidationErrors(['location_ids.0']);
    }

    public function test_a_location_deactivated_later_stays_on_the_jobs_that_hold_it(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);
        $this->api('POST', "/company/locations/{$this->jakartaId}/deactivate")->assertOk();

        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Backend Engineer'])->assertOk()->assertJsonPath('data.location_ids', [$this->bandungId, $this->jakartaId]);
        $this->api('PATCH', "/company/jobs/{$id}", ['location_ids' => [$this->jakartaId, $this->bandungId]])->assertOk()->assertJsonPath('data.location_ids', [$this->jakartaId, $this->bandungId]);

        // A new job cannot choose it any more.
        $this->api('POST', '/company/jobs', $this->body([$this->jakartaId]))->assertStatus(422)->assertJsonStructure(['errors' => ['location_ids']]);
    }

    public function test_a_location_that_a_job_holds_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $this->create([$this->bandungId]);

        $this->api('DELETE', "/company/locations/{$this->bandungId}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:location-in-use');
        $this->api('POST', "/company/locations/{$this->bandungId}/deactivate")->assertOk();
        $this->api('DELETE', "/company/locations/{$this->jakartaId}")->assertNoContent();
    }

    public function test_the_job_keeps_the_place_as_it_was_saved_until_it_is_saved_again(): void
    {
        $id = $this->create([$this->bandungId]);
        $this->api('PATCH', "/company/locations/{$this->bandungId}", ['name' => 'Bandung Kota', 'address_line' => 'Jl. Baru 9'])->assertOk();

        $this->api('GET', "/company/jobs/{$id}")->assertOk()->assertJsonPath('data.locations.0.name', 'Cabang Bandung')->assertJsonPath('data.locations.0.address_line', 'Jl. Setiabudi 1');

        $this->api('PATCH', "/company/jobs/{$id}", ['location_ids' => [$this->bandungId]])->assertOk()
            ->assertJsonPath('data.locations.0.name', 'Bandung Kota')->assertJsonPath('data.address_line', 'Jl. Baru 9');
    }

    public function test_a_clone_keeps_the_locations_and_a_deleted_draft_takes_them_away(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);

        $copy = (int) $this->api('POST', "/company/jobs/{$id}/clone")->assertCreated()->json('data.id');

        $this->assertSame([$this->bandungId, $this->jakartaId], DB::table('job_job_locations')->where('job_id', $copy)->orderBy('position')->pluck('location_id')->map(fn ($value): int => (int) $value)->all());

        $this->api('DELETE', "/company/jobs/{$id}")->assertNoContent();
        $this->assertSame(0, DB::table('job_job_locations')->where('job_id', $id)->count());
        $this->assertSame(2, DB::table('job_job_locations')->where('job_id', $copy)->count());
    }

    public function test_changing_the_locations_of_a_live_job_of_a_company_that_needs_approval_sends_it_back_to_review(): void
    {
        $id = $this->create([$this->bandungId]);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'active');
        $this->app->make(SettingsStore::class)->setCompanyOverride(JobSettings::REQUIRE_APPROVAL, $this->companyId, true, null);

        $this->api('PATCH', "/company/jobs/{$id}", ['location_ids' => [$this->bandungId, $this->jakartaId]])->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    // ----- what the public sees -----

    public function test_the_public_page_lists_every_location_with_its_address(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $locations = $this->publicJob($id)->assertOk()->json('data.locations');

        $this->assertCount(2, $locations);
        $this->assertSame(['id', 'name', 'type', 'province', 'city', 'subdistrict', 'village', 'postal_code', 'address_line'], array_keys($locations[0]));
        $this->assertSame([$this->bandungId, 'Cabang Bandung', 'JAWA BARAT', 'Jl. Setiabudi 1'], [$locations[0]['id'], $locations[0]['name'], $locations[0]['province'], $locations[0]['address_line']]);
        $this->assertSame([$this->jakartaId, 'Kantor Jakarta', 'site', 'DKI JAKARTA', 'JAKARTA SELATAN', null], [$locations[1]['id'], $locations[1]['name'], $locations[1]['type'], $locations[1]['province'], $locations[1]['city'], $locations[1]['subdistrict']]);
    }

    public function test_a_search_row_lists_every_location_with_its_city_and_province(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $row = $this->getJson('/api/v1/jobs')->assertOk()->json('data.0');

        $this->assertSame([
            ['id' => $this->bandungId, 'name' => 'Cabang Bandung', 'type' => 'branch', 'city' => 'BANDUNG', 'province' => 'JAWA BARAT'],
            ['id' => $this->jakartaId, 'name' => 'Kantor Jakarta', 'type' => 'site', 'city' => 'JAKARTA SELATAN', 'province' => 'DKI JAKARTA'],
        ], $row['locations']);
    }

    public function test_the_search_finds_a_job_through_any_of_its_locations(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $plain = (int) $this->api('POST', '/company/jobs', [...$this->fullJobBody('Plain Bandung Job')])->assertCreated()->json('data.id');
        $this->api('POST', "/company/jobs/{$plain}/publish")->assertOk();

        $this->assertSame(['Backend Developer', 'Plain Bandung Job'], $this->sorted($this->search('filter[province_id]='.$this->bandung['province_id'])));
        $this->assertSame(['Backend Developer'], $this->search('filter[province_id]='.$this->jakarta['province_id']));
        $this->assertSame(['Backend Developer'], $this->search('filter[city_id]='.$this->jakarta['city_id']));
        // The filters are independent: the province can be matched by one location and the city by another.
        $this->assertSame(['Backend Developer'], $this->search('filter[city_id]='.$this->jakarta['city_id'].'&filter[province_id]='.$this->bandung['province_id']));
        $this->assertSame([], $this->search('filter[city_id]=999999'));
    }

    public function test_the_company_list_finds_a_job_through_any_of_its_locations(): void
    {
        $this->create([$this->bandungId, $this->jakartaId]);

        $titles = fn (string $query): array => array_column($this->api('GET', '/company/jobs?'.$query)->assertOk()->json('data'), 'title');

        $this->assertSame(['Backend Developer'], $titles('filter[province_id]='.$this->jakarta['province_id']));
        $this->assertSame([], $titles('filter[province_id]=999999'));
    }

    public function test_the_preview_shows_the_locations(): void
    {
        $id = $this->create([$this->bandungId, $this->jakartaId]);

        $this->api('GET', "/company/jobs/{$id}/preview")->assertOk()->assertJsonCount(2, 'data.locations')->assertJsonPath('data.locations.1.name', 'Kantor Jakarta');
    }

    /** @param  list<string>  $titles @return list<string> */
    private function sorted(array $titles): array
    {
        sort($titles);

        return $titles;
    }
}
