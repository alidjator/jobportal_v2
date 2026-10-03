<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Shared\Application\Contracts\UserProfileProjection;

class CompanyProfileTest extends CompanyTestCase
{
    public function test_company_endpoints_require_authentication_and_company_role(): void
    {
        foreach (['/api/company', '/api/company/dashboard', '/api/company/social-links', '/api/company/account-progress'] as $url) {
            $this->getJson($url)->assertUnauthorized()->assertJsonStructure(['error']);
        }

        $candidate = $this->makeCandidate();
        Sanctum::actingAs($candidate['user']);
        $this->getJson('/api/company')->assertUnauthorized();
    }

    public function test_profile_has_company_resource_shape(): void
    {
        $made = $this->actingAsCompany(['bio' => 'About us', 'website' => 'https://x.test', 'country' => 'Indonesia', 'logo' => 'images/company/a.png']);
        DB::table('social_links')->insert(['user_id' => $made['user']->id, 'social_media' => 'facebook', 'url' => 'https://fb.test/x', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contact_infos')->insert(['user_id' => $made['user']->id, 'phone' => '0812345', 'email' => 'c@x.test', 'created_at' => now(), 'updated_at' => now()]);

        $data = $this->getJson('/api/company')->assertOk()->json('data');

        $this->assertSame($made['company'], $data['id']);
        $this->assertSame($made['user']->username, $data['username']);
        $this->assertSame('company', $data['role']);
        $this->assertSame('About us', $data['bio']);
        $this->assertSame('Indonesia', $data['country']);
        $this->assertStringEndsWith('images/company/a.png', $data['photo_url']);
        $this->assertNull($data['user_plan']);
        $this->assertSame([['social_media' => 'facebook', 'url' => 'https://fb.test/x']], $data['social']);
        $this->assertSame('0812345', $data['contactinfo']['phone']);
        $this->assertArrayNotHasKey('user_id', $data['contactinfo']);
        $this->assertArrayHasKey('first_name', $data);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_projection_is_tagged_for_identity(): void
    {
        $made = $this->makeCompany();

        $projection = collect(app()->tagged('user-profile-projections'))
            ->first(fn (UserProfileProjection $p) => $p->role() === 'company');

        $this->assertNotNull($projection);
        $this->assertSame($made['company'], $projection->project($made['user']->id)['id']);
        $this->assertSame([], $projection->project(0));
    }

    public function test_dashboard_counts_jobs_and_saved_candidates(): void
    {
        $made = $this->actingAsCompany();
        $a = $this->makeJob($made['company']);
        $this->makeJob($made['company']);
        $this->makeJob($made['company'], ['status' => 'pending']);
        $other = $this->makeCompany();
        $this->makeJob($other['company']);
        $candidate = $this->makeCandidate();
        DB::table('bookmark_company')->insert(['company_id' => $made['company'], 'candidate_id' => $candidate['candidate'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('applied_jobs')->insert(['job_id' => $a, 'candidate_id' => $candidate['candidate'], 'created_at' => now(), 'updated_at' => now()]);

        $data = $this->getJson('/api/company/dashboard')->assertOk()->json('data');

        $this->assertSame(2, $data['openJobCount']);
        $this->assertSame(1, $data['savedCandidateCount']);
        $this->assertCount(3, $data['recentJobs']);
        $this->assertSame(1, collect($data['recentJobs'])->firstWhere('id', $a)['applied_jobs_count']);
        $this->assertSame($made['company'], $data['recentJobs'][0]['company_id']);
    }

    public function test_social_links(): void
    {
        $made = $this->actingAsCompany();
        $id = DB::table('social_links')->insertGetId(['user_id' => $made['user']->id, 'social_media' => 'github', 'url' => 'https://gh.test/x', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson('/api/company/social-links')->assertOk()
            ->assertExactJson(['data' => ['social_media' => [['id' => $id, 'social_media' => 'github', 'url' => 'https://gh.test/x']]]]);
    }
}
