<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class CompanyDirectoryTest extends CompanyTestCase
{
    private function named(string $name, array $company = [], array $user = []): array
    {
        return $this->makeCompany($user + ['name' => $name], $company);
    }

    public function test_list_is_public_and_hides_sensitive_columns(): void
    {
        $tag = Str::random(8);
        $made = $this->named("Zeta {$tag}", ['country' => 'Indonesia'], ['email' => "secret{$tag}@example.test", 'firebase_uid' => 'fb-secret']);
        $this->makeJob($made['company']);
        $this->makeJob($made['company']);
        $this->makeJob($made['company'], ['status' => 'pending']);

        $response = $this->getJson('/api/companies?keyword='.$tag)->assertOk();
        $item = $response->json('data.data.0');

        $this->assertSame(['id', 'name', 'username', 'country', 'logo_url', 'activejobs'], array_keys($item));
        $this->assertSame(2, $item['activejobs']);
        $this->assertSame($made['user']->username, $item['username']);
        $this->assertStringNotContainsString('secret', $response->getContent());
        $response->assertJsonPath('data.per_page', 12)->assertJsonPath('data.total', 1);
    }

    public function test_list_only_active_companies_and_keyword_filter(): void
    {
        $tag = Str::random(8);
        $this->named("Alpha {$tag}");
        $this->named("Hidden {$tag}", ['visibility' => 0]);
        $this->named("Banned {$tag}", [], ['status' => 0]);

        $names = collect($this->getJson('/api/companies?keyword='.$tag)->assertOk()->json('data.data'))->pluck('name')->all();

        $this->assertSame(["Alpha {$tag}"], $names);
    }

    public function test_list_filters_and_sorting_and_pagination(): void
    {
        $tag = Str::random(8);
        $a = $this->named("Aa {$tag}", ['country' => 'Narnia', 'created_at' => now()->subDays(3)]);
        $b = $this->named("Bb {$tag}", ['country' => 'Mordor', 'created_at' => now()->subDays(1)]);
        DB::table('industry_type_translations')->insert(['industry_type_id' => $a['industry'], 'name' => "Ind {$tag}", 'locale' => 'en']);
        DB::table('organization_type_translations')->insert(['organization_type_id' => $b['organization'], 'name' => "Org {$tag}", 'locale' => 'en']);

        $names = fn (string $qs) => collect($this->getJson("/api/companies?keyword={$tag}&{$qs}")->assertOk()->json('data.data'))->pluck('name')->all();

        $this->assertSame(["Bb {$tag}", "Aa {$tag}"], $names('sortBy=latest'));
        $this->assertSame(["Aa {$tag}", "Bb {$tag}"], $names('sortBy=oldest'));
        $this->assertSame(["Aa {$tag}"], $names('industry_type='.urlencode("Ind {$tag}")));
        $this->assertSame(["Bb {$tag}"], $names('organization_type='.urlencode("Org {$tag}")));
        $this->assertSame(["Bb {$tag}"], $names('location=Mordor'));
        $this->assertSame([], $names('industry_type=nonexistent-'.$tag));
        $this->getJson("/api/companies?keyword={$tag}&paginate=1")->assertOk()->assertJsonPath('data.last_page', 2)->assertJsonCount(1, 'data.data');
    }

    public function test_location_radius_does_not_bypass_active_scope(): void
    {
        $tag = Str::random(8);
        $near = $this->named("Near {$tag}", ['lat' => -6.2, 'long' => 106.8]);
        $far = $this->named("Far {$tag}", ['lat' => 40.7, 'long' => -74.0]);
        $this->named("Hidden {$tag}", ['lat' => -6.2, 'long' => 106.8, 'visibility' => 0]);

        $names = collect($this->getJson("/api/companies?keyword={$tag}&lat=-6.21&long=106.81")->assertOk()->json('data.data'))->pluck('name')->all();

        $this->assertSame(["Near {$tag}"], $names);
        $this->assertNotContains("Far {$tag}", $names);
        unset($near, $far);
    }

    public function test_list_validates_input_and_rejects_company_viewers(): void
    {
        $this->getJson('/api/companies?paginate=100000')->assertUnprocessable();
        $this->getJson('/api/companies?lat=abc&long=1')->assertUnprocessable();

        $this->actingAsCompany();
        $this->getJson('/api/companies')->assertStatus(400)->assertJsonStructure(['error']);

        Sanctum::actingAs($this->makeCandidate()['user']);
        $this->getJson('/api/companies')->assertOk();
    }

    public function test_list_country_header_limits_active_job_count(): void
    {
        $tag = Str::random(8);
        $made = $this->named("Cx {$tag}");
        $this->makeJob($made['company'], ['country' => 'Indonesia']);
        $this->makeJob($made['company'], ['country' => 'Japan']);

        $this->getJson("/api/companies?keyword={$tag}", ['selected_country' => 'Japan'])->assertJsonPath('data.data.0.activejobs', 1);
        $this->getJson("/api/companies?keyword={$tag}", ['selected_country' => 'all'])->assertJsonPath('data.data.0.activejobs', 2);
    }

    public function test_detail_returns_company_user_and_open_jobs_only(): void
    {
        $tag = Str::random(8);
        $made = $this->named("Detail {$tag}", ['bio' => 'Bio', 'country' => 'Indonesia'], ['email' => "leak{$tag}@example.test", 'google_id' => 'g-secret']);
        DB::table('contact_infos')->insert(['user_id' => $made['user']->id, 'phone' => '0811', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('social_links')->insert(['user_id' => $made['user']->id, 'social_media' => 'facebook', 'url' => 'https://fb.test', 'created_at' => now(), 'updated_at' => now()]);
        $open = $this->makeJob($made['company'], ['title' => 'Open role']);
        $pending = $this->makeJob($made['company'], ['status' => 'pending', 'title' => 'Draft role']);
        $expired = $this->makeJob($made['company'], ['deadline' => now()->subDay()->toDateString(), 'title' => 'Old role']);
        $viewer = $this->makeCandidate();
        DB::table('bookmark_candidate_company')->insert(['company_id' => $made['company'], 'candidate_id' => $viewer['candidate'], 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($viewer['user']);

        $response = $this->getJson('/api/companies/'.$made['user']->username)->assertOk();
        $data = $response->json('data');

        $this->assertSame($made['company'], $data['companyDetails']['id']);
        $this->assertSame('Bio', $data['companyDetails']['bio']);
        $this->assertSame(1, $data['companyDetails']['activejobs']);
        $this->assertTrue($data['companyDetails']['candidatemarked']);
        $this->assertArrayHasKey('organization', $data['companyDetails']);
        $this->assertArrayHasKey('industry', $data['companyDetails']);
        $this->assertSame($made['user']->username, $data['user']['username']);
        $this->assertSame('0811', $data['user']['contact_info']['phone']);
        $this->assertSame('facebook', $data['user']['social_info'][0]['social_media']);
        $this->assertSame([$open], collect($data['open_jobs']['data'])->pluck('id')->all());
        $this->assertNotContains($pending, collect($data['open_jobs']['data'])->pluck('id')->all());
        $this->assertNotContains($expired, collect($data['open_jobs']['data'])->pluck('id')->all());
        $this->assertSame($made['user']->username, $data['open_jobs']['data'][0]['company']['user']['username']);
        $this->assertStringNotContainsString("leak{$tag}", $response->getContent());
        $this->assertStringNotContainsString('g-secret', $response->getContent());
    }

    public function test_detail_candidatemarked_false_for_guests_and_404s(): void
    {
        $made = $this->named('Guest View '.Str::random(6));

        $this->getJson('/api/companies/'.$made['user']->username)->assertOk()->assertJsonPath('data.companyDetails.candidatemarked', false);
        $this->getJson('/api/companies/nobody-'.uniqid())->assertNotFound()->assertJsonStructure(['error']);

        // kandidat (bukan perusahaan) dan perusahaan tersembunyi -> 404
        $this->getJson('/api/companies/'.$this->makeCandidate()['user']->username)->assertNotFound();
        $hidden = $this->named('Hidden '.Str::random(6), ['visibility' => 0]);
        $this->getJson('/api/companies/'.$hidden['user']->username)->assertNotFound();
    }
}
