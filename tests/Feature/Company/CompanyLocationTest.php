<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Company\Application\Images\CompanySettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/company-locations.md` sections 1 and 3: the work locations of a company, their rules and the company activity log entries. */
final class CompanyLocationTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $body */
    private function create(array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', '/company/locations', ['name' => 'Cabang Bandung', 'type' => 'branch', ...$body], $token ?? $this->adminToken);
    }

    private function createdId(array $body = []): int
    {
        return (int) $this->create($body)->assertCreated()->json('data.id');
    }

    private function mainId(?int $companyId = null): int
    {
        return (int) DB::table('company_locations')->where('company_id', $companyId ?? $this->companyId)->where('is_main', true)->value('id');
    }

    private function audit(): array
    {
        return DB::table('platform_audit_logs')->where('action', 'company.location_changed')->orderBy('id')->get()
            ->map(fn (object $row): array => json_decode((string) $row->metadata, true))->all();
    }

    private function usedByAnInterview(int $locationId): void
    {
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $application = (int) $this->apply($job)->assertCreated()->json('data.id');
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');

        $this->api('POST', "/company/applications/{$application}/move", [
            'stage_id' => $stage, 'interview' => $this->interviewSchedule(['format' => 'onsite', 'location_id' => $locationId]),
        ], $this->recruiterToken)->assertOk();
    }

    // ----- reading -----

    public function test_the_routes_need_a_signed_in_user(): void
    {
        // The fixtures leave the token of the last request on the test client.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/company/locations')->assertStatus(401);
        $this->postJson('/api/v1/company/locations', [])->assertStatus(401);
        $this->deleteJson('/api/v1/company/locations/1')->assertStatus(401);
    }

    public function test_any_member_reads_the_list_with_the_main_location_first(): void
    {
        $second = $this->createdId(['name' => 'Bandung']);
        $this->createdId(['name' => 'Aceh']);
        $this->api('POST', "/company/locations/{$second}/deactivate", [], $this->adminToken)->assertOk();

        foreach ([$this->adminToken, $this->recruiterToken] as $token) {
            $names = $this->api('GET', '/company/locations', [], $token)->assertOk()->json('data.*.name');
            $this->assertSame(['Kantor Pusat', 'Aceh', 'Bandung'], $names);
        }

        $this->api('GET', '/company/locations?filter[status]=inactive', [], $this->recruiterToken)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bandung');
        $this->api('GET', '/company/locations?filter[status]=gone', [], $this->recruiterToken)->assertStatus(422);
        $this->api('GET', '/company/locations', [], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
    }

    public function test_a_company_only_sees_its_own_locations(): void
    {
        $other = $this->mainId($this->otherCompanyId);

        $this->assertNotSame(0, $other);
        $this->assertSame(['Kantor Pusat'], $this->api('GET', '/company/locations', [], $this->adminToken)->json('data.*.name'));
        $this->api('PATCH', "/company/locations/{$other}", ['name' => 'Taken over'], $this->adminToken)->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:location-not-found');
        $this->api('POST', "/company/locations/{$other}/deactivate", [], $this->adminToken)->assertNotFound();
        $this->api('DELETE', "/company/locations/{$other}", [], $this->adminToken)->assertNotFound();
    }

    // ----- creating -----

    public function test_a_company_admin_adds_a_location_with_its_address_phone_and_coordinates(): void
    {
        $chain = $this->locationChain();

        $this->create([
            'name' => '  Cabang Bandung ', 'address_line' => 'Jl. Setiabudi 1', ...$chain, 'phone' => '+62 22 555 0100', 'latitude' => -6.8915, 'longitude' => 107.6107,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Cabang Bandung')
            ->assertJsonPath('data.type', 'branch')
            ->assertJsonPath('data.is_main', false)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.address.address_line', 'Jl. Setiabudi 1')
            ->assertJsonPath('data.address.province', ['id' => $chain['province_id'], 'name' => 'JAWA BARAT'])
            ->assertJsonPath('data.address.city', ['id' => $chain['city_id'], 'name' => 'BANDUNG'])
            ->assertJsonPath('data.address.village', ['id' => $chain['village_id'], 'name' => 'GEGERKALONG'])
            ->assertJsonPath('data.address.postal_code', ['id' => $chain['postal_code_id'], 'code' => '40153'])
            ->assertJsonPath('data.phone', '+62 22 555 0100')
            ->assertJsonPath('data.coordinates', ['latitude' => -6.8915, 'longitude' => 107.6107])
            ->assertJsonPath('data.time_zone', 'Asia/Jakarta');

        $this->assertSame(2, DB::table('company_locations')->where('company_id', $this->companyId)->count());
        $this->assertSame(1, DB::table('company_locations')->where('company_id', $this->companyId)->where('is_main', true)->count());
    }

    public function test_a_location_may_be_created_with_the_name_and_the_type_only(): void
    {
        $this->create(['type' => 'site'])->assertCreated()
            ->assertJsonPath('data.address.province', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.coordinates', null)
            ->assertJsonPath('data.time_zone', null);
    }

    public function test_the_time_zone_follows_the_province(): void
    {
        $now = ['created_at' => now(), 'updated_at' => now()];
        $bali = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '51', 'name' => 'BALI', ...$now]);
        $papua = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '91', 'name' => 'PAPUA', ...$now]);

        $this->create(['name' => 'Bali', 'province_id' => $bali])->assertCreated()->assertJsonPath('data.time_zone', 'Asia/Makassar');
        $this->create(['name' => 'Papua', 'province_id' => $papua])->assertCreated()->assertJsonPath('data.time_zone', 'Asia/Jayapura');
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidFields(): array
    {
        return [
            'name missing' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'type missing' => [['type' => null], 'type'],
            'type unknown' => [['type' => 'warehouse'], 'type'],
            'address too long' => [['address_line' => str_repeat('a', 256)], 'address_line'],
            'phone with letters' => [['phone' => '021-ABC-0100'], 'phone'],
            'latitude not a number' => [['latitude' => 'north', 'longitude' => 100], 'latitude'],
        ];
    }

    /** @param  array<string, mixed>  $body */
    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_refused_per_field(array $body, string $field): void
    {
        $this->create($body)->assertStatus(422)->assertJsonValidationErrors($field, 'errors');

        $this->assertSame(1, DB::table('company_locations')->where('company_id', $this->companyId)->count());
    }

    public function test_a_name_of_only_spaces_is_refused(): void
    {
        $this->create(['name' => '   '])->assertStatus(422);
    }

    public function test_an_address_that_is_not_one_matching_chain_is_refused(): void
    {
        $chain = $this->locationChain();
        $other = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '12', 'name' => 'SUMATERA UTARA', 'created_at' => now(), 'updated_at' => now()]);

        // A city under the wrong province, a level without the level above it, and an id that does not exist.
        $this->create(['province_id' => $other, 'city_id' => $chain['city_id']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-location');
        $this->create(['city_id' => $chain['city_id']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-location');
        $this->create(['province_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-location');

        $this->assertSame(1, DB::table('company_locations')->where('company_id', $this->companyId)->count());
    }

    public function test_the_coordinates_come_as_a_pair_inside_indonesia(): void
    {
        foreach ([['latitude' => -6.9], ['longitude' => 107.6], ['latitude' => 51.5, 'longitude' => -0.12], ['latitude' => -11.01, 'longitude' => 100], ['latitude' => 0, 'longitude' => 141.01]] as $body) {
            $this->create($body)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-coordinates');
        }

        // The corners of the box are inside.
        $this->create(['name' => 'Selatan Barat', 'latitude' => -11, 'longitude' => 95])->assertCreated();
        $this->create(['name' => 'Utara Timur', 'latitude' => 6, 'longitude' => 141])->assertCreated();
    }

    public function test_a_name_is_unique_within_a_company_ignoring_case_but_not_across_companies(): void
    {
        $this->create(['name' => 'Pabrik Jawa Timur', 'type' => 'site'])->assertCreated();

        $this->create(['name' => 'pabrik JAWA timur', 'type' => 'site'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:location-name-taken');
        $this->create(['name' => 'kantor pusat'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:location-name-taken');
        $this->create(['name' => 'Pabrik Jawa Timur', 'type' => 'site'], $this->otherAdminToken)->assertCreated();
    }

    public function test_the_limit_counts_every_location_including_inactive_ones(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(CompanySettings::MAX_LOCATIONS, 3, null);

        $first = $this->createdId(['name' => 'Satu']);
        $this->createdId(['name' => 'Dua']);
        $this->api('POST', "/company/locations/{$first}/deactivate", [], $this->adminToken)->assertOk();

        $this->create(['name' => 'Tiga'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:location-limit-reached');
        $this->assertSame(3, DB::table('company_locations')->where('company_id', $this->companyId)->count());

        $this->api('DELETE', "/company/locations/{$first}", [], $this->adminToken)->assertNoContent();
        $this->create(['name' => 'Tiga'])->assertCreated();
    }

    public function test_only_a_member_who_may_change_the_profile_manages_locations(): void
    {
        $id = $this->createdId();

        $this->create([], $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('PATCH', "/company/locations/{$id}", ['name' => 'X'], $this->recruiterToken)->assertForbidden();
        $this->api('POST', "/company/locations/{$id}/main", [], $this->recruiterToken)->assertForbidden();
        $this->api('POST', "/company/locations/{$id}/deactivate", [], $this->recruiterToken)->assertForbidden();
        $this->api('POST', "/company/locations/{$id}/activate", [], $this->recruiterToken)->assertForbidden();
        $this->api('DELETE', "/company/locations/{$id}", [], $this->recruiterToken)->assertForbidden();
        $this->create([], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
    }

    public function test_a_suspended_company_can_read_but_not_manage_its_locations(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->api('GET', '/company/locations', [], $this->adminToken)->assertOk();
        $this->create()->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-suspended');
    }

    // ----- editing -----

    public function test_only_the_fields_that_are_sent_change_and_a_null_clears_an_optional_field(): void
    {
        $chain = $this->locationChain();
        $id = $this->createdId(['address_line' => 'Jl. Lama 1', ...$chain, 'phone' => '022 555 0100', 'latitude' => -6.9, 'longitude' => 107.6]);

        $this->api('PATCH', "/company/locations/{$id}", ['name' => 'Cabang Baru', 'type' => 'site', 'address_line' => 'Jl. Baru 2'], $this->adminToken)->assertOk()
            ->assertJsonPath('data.name', 'Cabang Baru')
            ->assertJsonPath('data.type', 'site')
            ->assertJsonPath('data.address.address_line', 'Jl. Baru 2')
            ->assertJsonPath('data.address.city.id', $chain['city_id'])
            ->assertJsonPath('data.phone', '022 555 0100')
            ->assertJsonPath('data.coordinates', ['latitude' => -6.9, 'longitude' => 107.6]);

        $this->api('PATCH', "/company/locations/{$id}", ['phone' => null, 'latitude' => null, 'longitude' => null, 'village_id' => null, 'postal_code_id' => null], $this->adminToken)->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.coordinates', null)
            ->assertJsonPath('data.address.village', null)
            ->assertJsonPath('data.address.postal_code', null)
            ->assertJsonPath('data.address.subdistrict.id', $chain['subdistrict_id']);

        $this->api('PATCH', "/company/locations/{$id}", [], $this->adminToken)->assertOk()->assertJsonPath('data.name', 'Cabang Baru');
    }

    public function test_a_partial_address_change_is_checked_together_with_the_stored_rest(): void
    {
        $id = $this->createdId($this->locationChain());
        $other = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '12', 'name' => 'SUMATERA UTARA', 'created_at' => now(), 'updated_at' => now()]);

        // The stored city stays under the old province, so changing only the province breaks the chain.
        $this->api('PATCH', "/company/locations/{$id}", ['province_id' => $other], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-location');
        // Clearing the levels below works.
        $this->api('PATCH', "/company/locations/{$id}", ['province_id' => $other, 'city_id' => null, 'subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null], $this->adminToken)
            ->assertOk()->assertJsonPath('data.address.province.name', 'SUMATERA UTARA')->assertJsonPath('data.time_zone', 'Asia/Jakarta');
    }

    public function test_an_edit_keeps_the_rules_of_the_coordinates_and_the_name(): void
    {
        $id = $this->createdId(['latitude' => -6.9, 'longitude' => 107.6]);
        $this->createdId(['name' => 'Cabang Medan']);

        // Only one of the pair changes: it is checked with the stored other half.
        $this->api('PATCH', "/company/locations/{$id}", ['latitude' => 51.5], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-coordinates');
        $this->api('PATCH', "/company/locations/{$id}", ['latitude' => null], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-coordinates');
        $this->api('PATCH', "/company/locations/{$id}", ['name' => 'CABANG medan'], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:location-name-taken');
        // Its own name in another case is fine.
        $this->api('PATCH', "/company/locations/{$id}", ['name' => 'CABANG BANDUNG'], $this->adminToken)->assertOk()->assertJsonPath('data.name', 'CABANG BANDUNG');
        $this->api('PATCH', "/company/locations/{$id}", ['name' => null], $this->adminToken)->assertStatus(422);
    }

    // ----- the main location -----

    public function test_a_new_location_is_never_main_and_the_main_mark_moves_to_an_active_location(): void
    {
        $old = $this->mainId();
        $new = $this->createdId();

        $this->api('POST', "/company/locations/{$new}/main", [], $this->adminToken)->assertOk()->assertJsonPath('data.is_main', true);

        $this->assertSame($new, $this->mainId());
        $this->assertSame(0, (int) DB::table('company_locations')->where('id', $old)->value('is_main'));
        $this->assertSame(1, DB::table('company_locations')->where('company_id', $this->companyId)->where('is_main', true)->count());
        $this->api('GET', '/company/profile', [], $this->recruiterToken)->assertOk()->assertJsonPath('data.main_location.id', $new);
        $this->assertSame([['location_id' => $new, 'change' => 'main_set', 'fields' => []]], array_slice($this->audit(), -1));
    }

    public function test_an_inactive_location_cannot_become_main_and_making_the_main_one_main_again_changes_nothing(): void
    {
        $inactive = $this->createdId();
        $this->api('POST', "/company/locations/{$inactive}/deactivate", [], $this->adminToken)->assertOk();
        $before = count($this->audit());

        $this->api('POST', "/company/locations/{$inactive}/main", [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:location-inactive');
        $this->api('POST', '/company/locations/'.$this->mainId().'/main', [], $this->adminToken)->assertOk()->assertJsonPath('data.is_main', true);

        $this->assertSame($before, count($this->audit()));
    }

    public function test_the_main_location_can_be_neither_deactivated_nor_deleted(): void
    {
        $main = $this->mainId();

        $this->api('POST', "/company/locations/{$main}/deactivate", [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:location-is-main');
        $this->api('DELETE', "/company/locations/{$main}", [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:location-is-main');

        // Once the mark has moved, the old main location is an ordinary one.
        $new = $this->createdId();
        $this->api('POST', "/company/locations/{$new}/main", [], $this->adminToken)->assertOk();
        $this->api('POST', "/company/locations/{$main}/deactivate", [], $this->adminToken)->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    // ----- deactivating, activating, deleting -----

    public function test_a_location_is_deactivated_and_activated_again_and_repeating_changes_nothing(): void
    {
        $id = $this->createdId();

        $this->api('POST', "/company/locations/{$id}/deactivate", [], $this->adminToken)->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertNotNull(DB::table('company_locations')->where('id', $id)->value('deactivated_at'));
        $this->api('POST', "/company/locations/{$id}/deactivate", [], $this->adminToken)->assertOk();

        $this->api('POST', "/company/locations/{$id}/activate", [], $this->adminToken)->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertNull(DB::table('company_locations')->where('id', $id)->value('deactivated_at'));
        $this->api('POST', "/company/locations/{$id}/activate", [], $this->adminToken)->assertOk();
        $this->api('POST', '/company/locations/'.$this->mainId().'/activate', [], $this->adminToken)->assertOk();

        $this->assertSame(['created', 'deactivated', 'activated'], array_column($this->audit(), 'change'));
    }

    public function test_a_location_that_nothing_refers_to_is_deleted(): void
    {
        $id = $this->createdId();

        $this->api('DELETE', "/company/locations/{$id}", [], $this->adminToken)->assertNoContent();

        $this->assertSame(0, DB::table('company_locations')->where('id', $id)->count());
        $this->api('DELETE', "/company/locations/{$id}", [], $this->adminToken)->assertNotFound();
        $this->assertSame(['created', 'deleted'], array_column($this->audit(), 'change'));
    }

    public function test_a_location_that_an_interview_refers_to_can_only_be_deactivated(): void
    {
        $chain = $this->locationChain();
        $id = $this->createdId(['address_line' => 'Jl. Setiabudi 1', ...$chain]);
        $this->usedByAnInterview($id);

        $this->api('DELETE', "/company/locations/{$id}", [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:location-in-use');
        $this->api('POST', "/company/locations/{$id}/deactivate", [], $this->adminToken)->assertOk();

        $this->assertSame(1, DB::table('company_locations')->where('id', $id)->count());
        $this->assertSame($id, (int) DB::table('recruitment_interviews')->value('location_id'));
    }

    // ----- the activity log -----

    public function test_every_change_is_in_the_company_activity_log_with_what_changed(): void
    {
        $id = $this->createdId();
        $this->api('PATCH', "/company/locations/{$id}", ['name' => 'Cabang Baru', 'phone' => '022 555 0100'], $this->adminToken)->assertOk();
        // The same values again change and audit nothing.
        $this->api('PATCH', "/company/locations/{$id}", ['name' => 'Cabang Baru', 'phone' => '022 555 0100'], $this->adminToken)->assertOk();

        $this->assertSame(
            [['location_id' => $id, 'change' => 'created', 'fields' => []], ['location_id' => $id, 'change' => 'updated', 'fields' => ['name', 'phone']]],
            $this->audit(),
        );

        $this->api('GET', '/company/activity?filter[group]=settings', [], $this->adminToken)->assertOk()->assertJsonCount(2, 'data');
    }
}
