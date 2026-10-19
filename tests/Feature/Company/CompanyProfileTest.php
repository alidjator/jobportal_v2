<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Company\Domain\CompanyProfile;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

final class CompanyProfileTest extends FeatureTestCase
{
    private int $companyId;

    private int $industryId;

    private int $organizationTypeId;

    private int $teamSizeId;

    /** @var array{province: int, city: int, subdistrict: int, village: int, postal: int} */
    private array $place;

    private string $adminToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->industryId = $this->master('reference_industries', 'Teknologi', 'Technology');
        $this->organizationTypeId = $this->master('reference_organization_types', 'Perusahaan swasta', 'Private company');
        $this->teamSizeId = $this->master('reference_team_sizes', '11-50 orang', '11-50 people', ['min_size' => 11, 'max_size' => 50]);
        $this->place = $this->makePlace('11', 'ACEH', 'BANDA ACEH', 'MEURAXA', 'LAMBUNG', '23116');

        $this->companyId = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju Bersama', 'slug' => 'pt-maju-bersama', 'status' => 'active', 'industry_id' => $this->industryId,
            'organization_type_id' => $this->organizationTypeId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Every company has a main work location; the address lives there.
        DB::table('company_locations')->insert([
            'company_id' => $this->companyId, 'name' => 'Kantor Pusat', 'type' => 'head_office', 'is_main' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = $this->makeMember('Andi Admin', 'andi@example.test', 'company_admin');
        $recruiter = $this->makeMember('Rina Rekruter', 'rina@example.test', 'recruiter');
        $this->adminToken = $admin->createToken('phone', ['*'], now()->addDay())->plainTextToken;
        $this->recruiterToken = $recruiter->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $extra */
    private function master(string $table, string $id, string $en, array $extra = [], bool $active = true): int
    {
        return (int) DB::table($table)->insertGetId([
            'name' => json_encode(['id' => $id, 'en' => $en]), 'slug' => strtolower(str_replace(' ', '-', $en)), 'sort_order' => 0, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    /** @return array{province: int, city: int, subdistrict: int, village: int, postal: int} */
    private function makePlace(string $code, string $province, string $city, string $subdistrict, string $village, string $postal): array
    {
        $now = ['created_at' => now(), 'updated_at' => now()];
        $provinceId = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => $code, 'name' => $province, ...$now]);
        $cityId = (int) DB::table('reference_cities')->insertGetId(['province_id' => $provinceId, 'bps_code' => $code.'71', 'name' => $city, 'type' => 'city', ...$now]);
        $subdistrictId = (int) DB::table('reference_subdistricts')->insertGetId(['city_id' => $cityId, 'bps_code' => $code.'7101', 'name' => $subdistrict, ...$now]);
        $villageId = (int) DB::table('reference_villages')->insertGetId(['subdistrict_id' => $subdistrictId, 'bps_code' => $code.'71012001', 'name' => $village, ...$now]);
        $postalId = (int) DB::table('reference_postal_codes')->insertGetId(['village_id' => $villageId, 'code' => $postal, ...$now]);

        return ['province' => $provinceId, 'city' => $cityId, 'subdistrict' => $subdistrictId, 'village' => $villageId, 'postal' => $postalId];
    }

    private function makeMember(string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($this->companyId, $user->id, $role);

        return $user;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, '/api/v1/company'.$uri, $body);
    }

    /** @param  array<string, mixed>  $body */
    private function update(array $body, ?string $token = null): TestResponse
    {
        return $this->api('PATCH', '/profile', $body, $token);
    }

    private function row(): object
    {
        return DB::table('company_companies')->where('id', $this->companyId)->first();
    }

    /** @return array<string, mixed> */
    private function fullLocation(): array
    {
        return [
            'province_id' => $this->place['province'], 'city_id' => $this->place['city'], 'subdistrict_id' => $this->place['subdistrict'],
            'village_id' => $this->place['village'], 'postal_code_id' => $this->place['postal'],
        ];
    }

    private function publicPage(string $slug = 'pt-maju-bersama', string $language = 'en'): TestResponse
    {
        return $this->withHeader('Accept-Language', $language)->getJson("/api/v1/companies/{$slug}");
    }

    // ----- reading the own profile -----

    public function test_the_own_profile_routes_need_a_signed_in_user(): void
    {
        $this->getJson('/api/v1/company/profile')->assertStatus(401);
        $this->patchJson('/api/v1/company/profile', [])->assertStatus(401);
        $this->putJson('/api/v1/company/slug', ['slug' => 'new-name'])->assertStatus(401);
    }

    public function test_any_member_reads_the_whole_profile_as_stored(): void
    {
        DB::table('company_companies')->update([
            'website' => 'https://maju.example', 'established_on' => '2015-03-17', 'bio' => "**Maju** bersama\n\n- satu", 'vision' => 'Jadi yang terbaik',
            'team_size_id' => $this->teamSizeId,
            'contact_email' => 'hr@maju.example', 'contact_phone' => '+62 21 555 0100', 'is_visible' => false, 'verified_at' => '2026-10-01 08:30:00',
        ]);
        DB::table('company_locations')->update(['address_line' => 'Jl. Merdeka 1', 'province_id' => $this->place['province'], 'city_id' => $this->place['city']]);

        foreach ([$this->adminToken, $this->recruiterToken] as $token) {
            $this->api('GET', '/profile', [], $token)->assertOk()
                ->assertJsonPath('data.id', $this->companyId)
                ->assertJsonPath('data.slug', 'pt-maju-bersama')
                ->assertJsonPath('data.status', 'active')
                ->assertJsonPath('data.name', 'PT Maju Bersama')
                ->assertJsonPath('data.website', 'https://maju.example')
                ->assertJsonPath('data.established_on', '2015-03-17')
                ->assertJsonPath('data.bio', "**Maju** bersama\n\n- satu")
                ->assertJsonPath('data.industry_id', $this->industryId)
                ->assertJsonPath('data.team_size_id', $this->teamSizeId)
                ->assertJsonPath('data.main_location.name', 'Kantor Pusat')
                ->assertJsonPath('data.main_location.is_main', true)
                ->assertJsonPath('data.main_location.address.address_line', 'Jl. Merdeka 1')
                ->assertJsonPath('data.main_location.address.province', ['id' => $this->place['province'], 'name' => 'ACEH'])
                ->assertJsonPath('data.main_location.address.village', null)
                ->assertJsonPath('data.main_location.time_zone', 'Asia/Jakarta')
                ->assertJsonPath('data.contact_email', 'hr@maju.example')
                ->assertJsonPath('data.contact_phone', '+62 21 555 0100')
                ->assertJsonPath('data.is_visible', false)
                ->assertJsonPath('data.verified_at', '2026-10-01T08:30:00+00:00');
        }
    }

    public function test_the_profile_can_be_read_while_the_company_is_suspended(): void
    {
        DB::table('company_companies')->update(['status' => 'suspended']);

        $this->api('GET', '/profile')->assertOk()->assertJsonPath('data.status', 'suspended');
    }

    public function test_a_job_seeker_and_a_member_without_a_company_cannot_read_a_company_profile(): void
    {
        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);
        $orphan = UserModel::query()->create(['type' => 'company_member', 'name' => 'Orphan', 'email' => 'orphan@example.test', 'status' => 'active', 'locale' => 'en']);

        foreach ([$seeker, $orphan] as $user) {
            $this->api('GET', '/profile', [], $user->createToken('phone', ['*'], now()->addDay())->plainTextToken)
                ->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        }
    }

    // ----- changing the profile -----

    public function test_a_recruiter_and_a_suspended_company_cannot_change_the_profile(): void
    {
        $this->update(['bio' => 'x'], $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('PUT', '/slug', ['slug' => 'other-name'], $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');

        DB::table('company_companies')->update(['status' => 'suspended']);
        $this->update(['bio' => 'x'])->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        $this->api('PUT', '/slug', ['slug' => 'other-name'])->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');

        $this->assertNull($this->row()->bio);
        $this->assertSame('pt-maju-bersama', $this->row()->slug);
    }

    public function test_only_the_fields_that_are_sent_change(): void
    {
        DB::table('company_companies')->update(['website' => 'https://old.example', 'bio' => 'Old bio', 'contact_phone' => '021 555 0100']);

        $this->update([
            'name' => '  PT Maju Jaya ', 'bio' => "Line one\n\nLine two", 'vision' => 'Vision', 'established_on' => '2015-03-17', 'team_size_id' => $this->teamSizeId,
            'contact_email' => 'hr@maju.example', 'is_visible' => false,
        ])->assertNoContent();

        $row = $this->row();
        $this->assertSame('PT Maju Jaya', $row->name);
        $this->assertSame("Line one\n\nLine two", $row->bio);
        $this->assertSame('Vision', $row->vision);
        $this->assertSame('2015-03-17', $row->established_on);
        $this->assertSame($this->teamSizeId, (int) $row->team_size_id);
        $this->assertSame('hr@maju.example', $row->contact_email);
        $this->assertSame(0, (int) $row->is_visible);
        // Not sent, so untouched; the slug never follows the name.
        $this->assertSame('https://old.example', $row->website);
        $this->assertSame('021 555 0100', $row->contact_phone);
        $this->assertSame('pt-maju-bersama', $row->slug);
        $this->assertSame($this->industryId, (int) $row->industry_id);
    }

    public function test_a_null_clears_an_optional_field_and_an_empty_request_changes_nothing(): void
    {
        DB::table('company_companies')->update(['website' => 'https://old.example', 'bio' => 'Old bio', 'team_size_id' => $this->teamSizeId, 'established_on' => '2015-03-17']);

        $this->update([])->assertNoContent();
        $this->assertSame('Old bio', $this->row()->bio);

        $this->update(['website' => null, 'bio' => null, 'team_size_id' => null, 'established_on' => null])->assertNoContent();

        $row = $this->row();
        $this->assertNull($row->website);
        $this->assertNull($row->bio);
        $this->assertNull($row->team_size_id);
        $this->assertNull($row->established_on);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidFields(): array
    {
        return [
            'name too short' => [['name' => 'A'], 'name'],
            'name cleared' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'website not http' => [['website' => 'ftp://files.example'], 'website'],
            'website not a url' => [['website' => 'maju example'], 'website'],
            'founded in the future' => [['established_on' => '2026-10-06'], 'established_on'],
            'founded in another format' => [['established_on' => '17/03/2015'], 'established_on'],
            'bio too long' => [['bio' => str_repeat('a', 5001)], 'bio'],
            'vision too long' => [['vision' => str_repeat('a', 5001)], 'vision'],
            'industry cleared' => [['industry_id' => null], 'industry_id'],
            'organization type not a number' => [['organization_type_id' => 'x'], 'organization_type_id'],
            'contact email invalid' => [['contact_email' => 'not-an-email'], 'contact_email'],
            'phone with letters' => [['contact_phone' => '021-ABC-0100'], 'contact_phone'],
            'phone too short' => [['contact_phone' => '12345'], 'contact_phone'],
            'visibility not a boolean' => [['is_visible' => 'maybe'], 'is_visible'],
            'visibility cleared' => [['is_visible' => null], 'is_visible'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_refused_per_field(array $body, string $field): void
    {
        $this->update($body)->assertStatus(422)->assertJsonValidationErrors($field, 'errors');

        $this->assertSame('PT Maju Bersama', $this->row()->name);
    }

    public function test_boundary_values_are_accepted(): void
    {
        $this->update([
            'name' => 'AB', 'bio' => str_repeat('a', 5000), 'established_on' => '2026-10-05', 'contact_phone' => '(021) 555-0100 +62',
            'website' => 'http://maju.example/about?x=1',
        ])->assertNoContent();

        $this->assertSame('AB', $this->row()->name);
        $this->assertSame(5000, strlen($this->row()->bio));
    }

    public function test_the_classifications_must_be_active_master_items(): void
    {
        $inactive = $this->master('reference_industries', 'Tutup', 'Closed', [], false);

        $this->update(['industry_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-industry');
        $this->update(['industry_id' => $inactive])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-industry');
        $this->update(['organization_type_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-organization-type');
        $this->update(['team_size_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-team-size');

        $this->assertSame($this->industryId, (int) $this->row()->industry_id);
    }

    public function test_an_item_deactivated_after_it_was_chosen_does_not_block_unrelated_edits(): void
    {
        DB::table('reference_industries')->where('id', $this->industryId)->update(['is_active' => false]);

        $this->update(['bio' => 'Still editable'])->assertNoContent();
        $this->update(['industry_id' => $this->industryId, 'bio' => 'Same industry again'])->assertStatus(422);
    }

    // ----- the address is the main location -----

    public function test_the_profile_no_longer_changes_the_address(): void
    {
        $this->update(['address_line' => 'Jl. Baru 2', ...$this->fullLocation(), 'bio' => 'New'])->assertNoContent();

        $main = DB::table('company_locations')->where('company_id', $this->companyId)->first();
        $this->assertNull($main->address_line);
        $this->assertNull($main->province_id);
        $this->assertSame('New', $this->row()->bio);
    }

    // ----- slug -----

    public function test_the_slug_can_be_changed_and_the_old_link_stops_working(): void
    {
        $this->update(['is_visible' => true])->assertNoContent();
        $this->publicPage()->assertOk();

        $this->api('PUT', '/slug', ['slug' => 'maju-jaya-2026'])->assertNoContent();

        $this->assertSame('maju-jaya-2026', $this->row()->slug);
        $this->publicPage('pt-maju-bersama')->assertNotFound();
        $this->publicPage('maju-jaya-2026')->assertOk()->assertJsonPath('data.slug', 'maju-jaya-2026');
        $this->api('GET', '/profile')->assertJsonPath('data.slug', 'maju-jaya-2026');
    }

    public function test_setting_the_slug_already_in_force_does_nothing(): void
    {
        $this->api('PUT', '/slug', ['slug' => 'pt-maju-bersama'])->assertNoContent();

        $this->assertSame('pt-maju-bersama', $this->row()->slug);
    }

    /** @return array<string, array{string}> */
    public static function badSlugs(): array
    {
        return [
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 61)],
            'upper case' => ['Maju-Jaya'],
            'space' => ['maju jaya'],
            'underscore' => ['maju_jaya'],
            'double hyphen' => ['maju--jaya'],
            'leading hyphen' => ['-maju'],
            'trailing hyphen' => ['maju-'],
            'non ascii' => ['majü-jaya'],
        ];
    }

    #[DataProvider('badSlugs')]
    public function test_a_badly_formed_slug_is_refused(string $slug): void
    {
        $this->api('PUT', '/slug', ['slug' => $slug])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-slug');

        $this->assertSame('pt-maju-bersama', $this->row()->slug);
    }

    public function test_the_slug_length_limits_are_inclusive(): void
    {
        $this->api('PUT', '/slug', ['slug' => 'abc'])->assertNoContent();
        $this->api('PUT', '/slug', ['slug' => str_repeat('a', 60)])->assertNoContent();
    }

    public function test_reserved_slugs_are_refused(): void
    {
        foreach (['admin', 'api', 'auth', 'login', 'register', 'company', 'companies', 'jobs', 'job', 'me', 'search', 'about', 'help', 'faq', 'terms', 'privacy', 'support', 'www'] as $slug) {
            $response = $this->api('PUT', '/slug', ['slug' => $slug]);

            // Words under three characters are refused for their length first.
            $response->assertStatus(422);
            $this->assertContains($response->json('type'), ['urn:onesis:error:slug-reserved', 'urn:onesis:error:invalid-slug']);
        }

        $this->api('PUT', '/slug', ['slug' => 'admin'])->assertJsonPath('type', 'urn:onesis:error:slug-reserved');
        $this->api('PUT', '/slug', ['slug' => 'jobs'])->assertJsonPath('type', 'urn:onesis:error:slug-reserved');
        $this->assertSame('pt-maju-bersama', $this->row()->slug);
    }

    public function test_a_slug_of_another_company_is_taken(): void
    {
        DB::table('company_companies')->insert([
            'name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active', 'industry_id' => $this->industryId,
            'organization_type_id' => $this->organizationTypeId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('PUT', '/slug', ['slug' => 'pt-lain'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:slug-taken');

        $this->assertSame('pt-maju-bersama', $this->row()->slug);
    }

    public function test_the_slug_needs_a_value(): void
    {
        $this->api('PUT', '/slug', [])->assertStatus(422);
        $this->api('PUT', '/slug', ['slug' => ''])->assertStatus(422);
    }

    // ----- the public page -----

    public function test_the_public_page_shows_the_profile_in_the_request_language_without_contact_data(): void
    {
        $this->update([
            'website' => 'https://maju.example', 'established_on' => '2015-03-17', 'bio' => 'Bio **bold**', 'vision' => 'Vision', 'team_size_id' => $this->teamSizeId,
            'contact_email' => 'hr@maju.example', 'contact_phone' => '+62 21 555 0100',
        ])->assertNoContent();
        DB::table('company_companies')->update(['verified_at' => '2026-10-01 08:30:00']);
        DB::table('company_locations')->update(['address_line' => 'Jl. Merdeka 1', 'phone' => '021 000 111', ...$this->fullLocation(), 'latitude' => 5.55, 'longitude' => 95.32]);

        $english = $this->publicPage()->assertOk();
        $english->assertJsonPath('data.name', 'PT Maju Bersama')
            ->assertJsonPath('data.slug', 'pt-maju-bersama')
            ->assertJsonPath('data.bio', 'Bio **bold**')
            ->assertJsonPath('data.vision', 'Vision')
            ->assertJsonPath('data.website', 'https://maju.example')
            ->assertJsonPath('data.established_on', '2015-03-17')
            ->assertJsonPath('data.industry', 'Technology')
            ->assertJsonPath('data.organization_type', 'Private company')
            ->assertJsonPath('data.team_size', '11-50 people')
            ->assertJsonPath('data.locations.0.name', 'Kantor Pusat')
            ->assertJsonPath('data.locations.0.type', 'head_office')
            ->assertJsonPath('data.locations.0.is_main', true)
            ->assertJsonPath('data.locations.0.address.address_line', 'Jl. Merdeka 1')
            ->assertJsonPath('data.locations.0.address.village', 'LAMBUNG')
            ->assertJsonPath('data.locations.0.address.subdistrict', 'MEURAXA')
            ->assertJsonPath('data.locations.0.address.city', 'BANDA ACEH')
            ->assertJsonPath('data.locations.0.address.province', 'ACEH')
            ->assertJsonPath('data.locations.0.address.postal_code', '23116')
            ->assertJsonPath('data.locations.0.coordinates', ['latitude' => 5.55, 'longitude' => 95.32])
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.verified_at', '2026-10-01T08:30:00+00:00');

        $this->publicPage(language: 'id')->assertOk()
            ->assertJsonPath('data.industry', 'Teknologi')
            ->assertJsonPath('data.team_size', '11-50 orang');

        $body = json_encode($english->json());
        $this->assertStringNotContainsString('hr@maju.example', $body);
        $this->assertStringNotContainsString('555 0100', $body);
        $this->assertStringNotContainsString('021 000 111', $body);
        $this->assertArrayNotHasKey('contact_email', $english->json('data'));
        $this->assertArrayNotHasKey('contact_phone', $english->json('data'));
        $this->assertArrayNotHasKey('id', $english->json('data'));
    }

    public function test_a_minimal_company_has_empty_optional_parts_and_no_badge(): void
    {
        $this->publicPage()->assertOk()
            ->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.team_size', null)
            ->assertJsonPath('data.locations.0.name', 'Kantor Pusat')
            ->assertJsonPath('data.locations.0.address.province', null)
            ->assertJsonPath('data.locations.0.address.postal_code', null)
            ->assertJsonPath('data.locations.0.coordinates', null)
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.verified_at', null)
            ->assertJsonPath('data.industry', 'Technology');
    }

    public function test_an_unknown_suspended_or_hidden_company_is_not_found(): void
    {
        $this->publicPage('no-such-company')->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:company-not-found');

        DB::table('company_companies')->update(['is_visible' => false]);
        $this->publicPage()->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:company-not-found');

        DB::table('company_companies')->update(['is_visible' => true, 'status' => 'suspended']);
        $this->publicPage()->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:company-not-found');

        DB::table('company_companies')->update(['status' => 'active']);
        $this->publicPage()->assertOk();
    }

    public function test_the_admin_can_hide_and_show_the_public_page(): void
    {
        $this->update(['is_visible' => false])->assertNoContent();
        $this->publicPage()->assertNotFound();

        $this->update(['is_visible' => true])->assertNoContent();
        $this->publicPage()->assertOk();
    }

    public function test_an_item_deactivated_after_it_was_chosen_still_shows_its_name(): void
    {
        DB::table('reference_industries')->where('id', $this->industryId)->update(['is_active' => false]);

        $this->publicPage()->assertOk()->assertJsonPath('data.industry', 'Technology');
    }

    public function test_the_public_page_can_be_cached_briefly_and_revalidated(): void
    {
        $response = $this->publicPage()->assertOk();

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=60', $cache);
        $etag = (string) $response->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->withHeaders(['If-None-Match' => $etag])->getJson('/api/v1/companies/pt-maju-bersama')->assertStatus(304);
    }

    public function test_the_public_page_needs_no_sign_in_even_when_a_token_is_sent(): void
    {
        $this->publicPage()->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->recruiterToken)->getJson('/api/v1/companies/pt-maju-bersama')->assertOk();
    }

    // ----- audit and domain -----

    public function test_profile_and_slug_changes_are_not_audited(): void
    {
        $this->update(['bio' => 'New', 'is_visible' => false])->assertNoContent();
        $this->api('PUT', '/slug', ['slug' => 'maju-jaya'])->assertNoContent();

        $this->assertSame(0, DB::table('platform_audit_logs')->count());
    }

    public function test_the_profile_value_refuses_an_unknown_field(): void
    {
        $profile = CompanyProfile::minimal('PT Maju', 1, 1);

        $this->assertSame('PT Baru', $profile->with(['name' => 'PT Baru'])->name);
        $this->assertSame('PT Maju', $profile->name);

        $this->expectException(InvalidArgumentException::class);
        $profile->with(['nonsense' => 1]);
    }
}
