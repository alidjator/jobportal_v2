<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class PublicJobTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** A complete job that is live, created and published through the company API. */
    private function seededAdminId(): int
    {
        return (int) (AdminModel::query()->where('email', 'seeded-admin@example.test')->value('id')
            ?? AdminModel::query()->create(['name' => 'Seeded', 'email' => 'seeded-admin@example.test', 'password' => 'hash', 'status' => 'active'])->id);
    }

    private function liveJob(string $title = 'Senior Backend Developer', array $override = []): int
    {
        $id = (int) $this->api('POST', '/company/jobs', [...$this->fullJobBody($title), ...$override])->assertCreated()->json('data.id');
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'active');

        return $id;
    }

    /** @param  array<string, string>  $headers */
    private function publicJob(string $identifier, array $headers = []): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->getJson("/api/v1/jobs/{$identifier}", $headers);
    }

    // ----- what it shows -----

    public function test_a_live_job_is_shown_to_anyone_without_signing_in(): void
    {
        $body = $this->fullJobBody();
        $id = $this->liveJob();

        $response = $this->publicJob((string) $id)->assertOk();
        $data = $response->json('data');

        $this->assertSame($id, $data['id']);
        $this->assertSame("senior-backend-developer-{$id}", $data['slug']);
        $this->assertSame(['title' => $body['title'], 'description' => $body['description'], 'vacancies' => 3, 'deadline' => '2026-12-31', 'work_mode' => 'hybrid'], [
            'title' => $data['title'], 'description' => $data['description'], 'vacancies' => $data['vacancies'], 'deadline' => $data['deadline'], 'work_mode' => $data['work_mode'],
        ]);
        $this->assertSame('2026-10-05T09:00:00+00:00', $data['published_at']);
        $this->assertSame(['php', 'laravel', 'c++'], $data['tags']);
    }

    public function test_the_classifications_are_names_with_their_ids_in_the_request_language(): void
    {
        $body = $this->fullJobBody();
        DB::table('reference_job_categories')->where('id', $body['category_id'])->update(['name' => json_encode(['id' => 'Rekayasa', 'en' => 'Engineering'])]);
        DB::table('reference_skills')->where('id', $body['skill_ids'][0])->update(['name' => json_encode(['id' => 'PHP (id)', 'en' => 'PHP (en)'])]);
        $id = $this->liveJob();

        $indonesian = $this->publicJob((string) $id, ['Accept-Language' => 'id'])->assertOk()->json('data');
        $english = $this->publicJob((string) $id, ['Accept-Language' => 'en'])->assertOk()->json('data');

        $this->assertSame(['id' => $body['category_id'], 'name' => 'Rekayasa'], $indonesian['category']);
        $this->assertSame(['id' => $body['category_id'], 'name' => 'Engineering'], $english['category']);
        $this->assertSame('PHP (id)', $indonesian['skills'][0]['name']);
        $this->assertSame('PHP (en)', $english['skills'][0]['name']);

        foreach (['role', 'job_type', 'job_level', 'work_time', 'experience_level', 'education_level'] as $field) {
            $this->assertSame(['id', 'name'], array_keys($english[$field]), $field);
        }
        $this->assertSame([['id' => $body['benefit_ids'][0], 'name' => 'insurance']], $english['benefits']);
    }

    public function test_the_location_is_names_not_ids(): void
    {
        $data = $this->publicJob((string) $this->liveJob())->assertOk()->json('data.locations');

        $this->assertSame(
            [['id' => null, 'name' => null, 'type' => null, 'province' => 'JAWA BARAT', 'city' => 'BANDUNG', 'subdistrict' => 'SUKASARI', 'village' => 'GEGERKALONG', 'postal_code' => '40153', 'address_line' => 'Jl. Setiabudi 1']],
            $data,
        );
    }

    public function test_a_remote_job_without_a_location_has_no_locations(): void
    {
        $id = $this->liveJob(override: ['work_mode' => 'remote', 'province_id' => null, 'city_id' => null, 'subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null, 'address_line' => null]);

        $data = $this->publicJob((string) $id)->assertOk()->json('data');

        $this->assertSame('remote', $data['work_mode']);
        $this->assertSame([], $data['locations']);
    }

    public function test_the_salary_is_shown_only_when_the_company_shows_it(): void
    {
        $period = $this->referenceId('reference_salary_periods', 'monthly');
        $visible = $this->liveJob('Visible', ['salary_visible' => true]);
        $hidden = $this->liveJob('Hidden', ['salary_visible' => false]);
        $none = $this->liveJob('None', ['salary_min' => null, 'salary_max' => null, 'salary_period_id' => null, 'salary_visible' => true]);
        $onlyMin = $this->liveJob('Only min', ['salary_min' => 8000000, 'salary_max' => null, 'salary_visible' => true]);

        $this->assertSame(['min' => 10000000, 'max' => 15000000, 'period' => ['id' => $period, 'name' => 'monthly']], $this->publicJob((string) $visible)->json('data.salary'));
        $this->assertNull($this->publicJob((string) $hidden)->json('data.salary'));
        $this->assertNull($this->publicJob((string) $none)->json('data.salary'));
        $this->assertSame(['min' => 8000000, 'max' => null, 'period' => ['id' => $period, 'name' => 'monthly']], $this->publicJob((string) $onlyMin)->json('data.salary'));
        $this->assertStringNotContainsString('10000000', (string) $this->publicJob((string) $hidden)->getContent());
    }

    public function test_the_company_card_has_the_name_the_badge_and_the_logo_and_no_contact_data(): void
    {
        $id = $this->liveJob();
        DB::table('company_companies')->where('id', $this->companyId)->update([
            'verified_at' => '2026-10-02 08:00:00', 'logo_path' => 'company/logos/abc123.png', 'contact_email' => 'secret@example.test', 'contact_phone' => '+62 812 3456 789',
        ]);

        $response = $this->publicJob((string) $id)->assertOk();
        $company = $response->json('data.company');

        $this->assertSame(['id', 'name', 'slug', 'logo_url', 'verified'], array_keys($company));
        $this->assertSame([$this->companyId, 'PT-MAJU', 'pt-maju', true], [$company['id'], $company['name'], $company['slug'], $company['verified']]);
        $this->assertStringEndsWith('/api/v1/companies/pt-maju/logo?v=abc123', $company['logo_url']);
        $this->assertStringStartsWith('http', $company['logo_url']);
        $this->assertStringNotContainsString('secret@example.test', (string) $response->getContent());
        $this->assertStringNotContainsString('812 3456', (string) $response->getContent());
    }

    public function test_a_company_without_a_logo_or_the_badge(): void
    {
        $company = $this->publicJob((string) $this->liveJob())->assertOk()->json('data.company');

        $this->assertNull($company['logo_url']);
        $this->assertFalse($company['verified']);
    }

    public function test_nothing_internal_is_shown(): void
    {
        $id = $this->liveJob();
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->recruiterId]])->assertOk();
        DB::table('job_jobs')->where('id', $id)->update(['status_reason' => 'Internal note.', 'reviewed_by_admin_id' => $this->seededAdminId(), 'reviewed_at' => '2026-10-04 08:00:00']);

        $json = $this->publicJob((string) $id)->assertOk()->getContent();

        foreach (['status', 'assignees', 'created_by_user_id', 'reviewed_by_admin_id', 'reviewed_at', 'Internal note', 'Rina', 'rina@example.test', 'category_id', 'province_id'] as $leak) {
            $this->assertStringNotContainsString($leak, (string) $json, $leak);
        }
    }

    public function test_a_benefit_that_is_not_approved_is_left_out(): void
    {
        $id = $this->liveJob();
        $pending = $this->referenceId('reference_benefits', 'gym', ['status' => 'pending', 'proposed_by_company_id' => $this->otherCompanyId]);
        DB::table('job_job_benefits')->insert(['job_id' => $id, 'benefit_id' => $pending]);

        $names = array_column($this->publicJob((string) $id)->json('data.benefits'), 'name');

        $this->assertSame(['insurance'], $names);
    }

    // ----- the identifier -----

    public function test_the_id_alone_or_followed_by_any_slug_finds_the_job(): void
    {
        $id = $this->liveJob();

        foreach ([(string) $id, "{$id}-senior-backend-developer", "{$id}-a-completely-different-slug", "{$id}-"] as $identifier) {
            $this->publicJob($identifier)->assertOk()->assertJsonPath('data.id', $id);
        }
    }

    public function test_an_identifier_that_is_not_an_id_with_a_slug_is_404(): void
    {
        $id = $this->liveJob();

        foreach (['abc', "{$id}abc", "{$id}-UPPER", "{$id}_x", "-{$id}", '0', '0-x', '999999', "{$id}.json"] as $identifier) {
            $this->publicJob($identifier)->assertNotFound();
        }
    }

    // ----- when it is hidden -----

    public function test_a_job_that_is_not_live_looks_like_one_that_does_not_exist(): void
    {
        $missing = $this->publicJob('999999')->assertNotFound();

        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status, attributes: ['deadline' => '2026-12-31']);

            $response = $this->publicJob((string) $id)->assertNotFound();
            $this->assertSame($missing->json('type'), $response->json('type'), $status);
            $this->assertStringNotContainsString("Job {$status}", (string) $response->getContent());
        }
    }

    public function test_a_suspended_or_hidden_company_hides_its_jobs_and_showing_it_again_brings_them_back(): void
    {
        $id = $this->liveJob();

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);
        $this->publicJob((string) $id)->assertNotFound();

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'active', 'is_visible' => false]);
        $this->publicJob((string) $id)->assertNotFound();

        DB::table('company_companies')->where('id', $this->companyId)->update(['is_visible' => true]);
        $this->publicJob((string) $id)->assertOk();
    }

    public function test_a_live_job_whose_deadline_has_passed_is_hidden_before_the_expiry_runs(): void
    {
        $id = $this->liveJob(override: ['deadline' => '2026-10-05']);

        $this->publicJob((string) $id)->assertOk();

        // 17:00 UTC is midnight in Jakarta: the deadline day is over, and the job is still `active` in the table.
        Carbon::setTestNow('2026-10-05 17:00:00');
        $this->assertSame('active', $this->jobRow($id)->status);
        $this->publicJob((string) $id)->assertNotFound();
    }

    public function test_a_closed_job_disappears_and_a_reopened_one_comes_back(): void
    {
        $id = $this->liveJob();
        $this->api('POST', "/company/jobs/{$id}/close")->assertOk();
        $this->publicJob((string) $id)->assertNotFound();

        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-12-20'])->assertOk();
        $this->publicJob((string) $id)->assertOk()->assertJsonPath('data.deadline', '2026-12-20');
    }

    // ----- caching and signed-in callers -----

    public function test_the_answer_can_be_cached_for_a_minute_and_revalidated_with_the_etag(): void
    {
        $id = $this->liveJob();

        $first = $this->publicJob((string) $id)->assertOk();
        $etag = $first->headers->get('ETag');

        $this->assertNotNull($etag);
        $this->assertStringContainsString('public', (string) $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', (string) $first->headers->get('Cache-Control'));

        $this->publicJob((string) $id, ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_a_signed_in_caller_sees_the_same_page_and_is_not_held_by_the_terms_gate(): void
    {
        $id = $this->liveJob();
        DB::table('content_legal_documents')->insert([
            'type' => 'terms', 'version' => 1, 'title' => json_encode(['id' => 'S', 'en' => 'T']), 'body' => json_encode(['id' => 'a', 'en' => 'b']),
            'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->recruiterToken)->getJson("/api/v1/jobs/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    }
}
