<?php

namespace Tests\Feature\Recruitment\JobManagement;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\FeatureTestCase;

class StoreJobTest extends FeatureTestCase
{
    use SeedsJobManagement;

    public function test_requires_authentication_and_company_role(): void
    {
        $this->postJson('/api/company/store/job', [])->assertUnauthorized()->assertExactJson(['error' => 'Unauthenticated']);

        Sanctum::actingAs($this->makeCompanyUser('candidate'));
        $this->postJson('/api/company/store/job', [])->assertUnauthorized();
    }

    public function test_creates_pending_job_with_location_and_slug(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $payload = $this->jobPayload(['title' => 'Same Title '.$this->companyId]);

        $response = $this->postJson('/api/company/store/job', $payload)->assertOk();

        $job = $response->json('data.data.job');
        $this->assertSame('Job Created Successfully', $response->json('data.data.message'));
        $this->assertSame('pending', $job['status']);
        $this->assertSame($this->companyId, $job['company_id']);
        $this->assertSame('same-title-'.$this->companyId, $job['slug']);
        $this->assertSame('Kota Bandung', explode(', ', $job['exact_location'])[1]);
        $this->assertSame('Coblong, Kota Bandung', $job['exact_location']);
        $this->assertSame($this->master['level'], $job['job_level_id']);
        $this->assertSame($this->master['subdistrict'], $job['subdistrict']);
        $this->assertSame($this->master['city'], $job['city_id']);
        $this->assertSame($this->master['state'], $job['province_id']);
        $this->assertSame('3', $job['vacancies']);
        $this->assertFalse($job['featured']);
        $this->assertArrayHasKey('days_remaining', $job);
        $this->assertTrue($job['can_apply']);
        $this->assertTrue($job['deadline_active']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->userId,
            'type' => 'App\Notifications\Website\Company\JobCreatedNotification',
        ]);

        // judul sama -> slug diberi akhiran unik
        $second = $this->postJson('/api/company/store/job', $payload)->assertOk()->json('data.data.job.slug');
        $this->assertNotSame('same-title-'.$this->companyId, $second);
        $this->assertStringStartsWith('same-title-'.$this->companyId.'-', $second);
    }

    public function test_job_is_active_when_auto_approved(): void
    {
        $this->settings(['job_auto_approved' => 1]);
        $this->actingAsCompany();

        $this->postJson('/api/company/store/job', $this->jobPayload())
            ->assertOk()
            ->assertJsonPath('data.data.job.status', 'active');
    }

    public function test_tags_are_created_or_reused_by_name(): void
    {
        $this->settings();
        DB::table('languages')->insert(['name' => 'Eng', 'code' => 'en', 'icon' => 'x', 'direction' => 'ltr']);
        $existing = DB::table('tags')->insertGetId([]);
        $label = 'Laravel'.random_int(1000, 9999999);
        DB::table('tag_translations')->insert(['tag_id' => $existing, 'name' => $label, 'locale' => 'en']);
        $this->actingAsCompany();
        $newLabel = 'BrandNewTag'.random_int(1000, 9999999);

        $jobId = $this->postJson('/api/company/store/job', $this->jobPayload(['tags' => [$label, $newLabel]]))
            ->assertOk()->json('data.data.job.id');

        $tagIds = DB::table('job_tag')->where('job_id', $jobId)->pluck('tag_id')->all();
        $this->assertCount(2, $tagIds);
        $this->assertContains($existing, $tagIds);
        $newId = DB::table('tag_translations')->where('name', $newLabel)->value('tag_id');
        $this->assertContains($newId, $tagIds);
        $this->assertDatabaseHas('tag_translations', ['tag_id' => $newId, 'locale' => 'en']);
    }

    public function test_validation_errors(): void
    {
        $this->settings();
        $this->actingAsCompany();

        $this->postJson('/api/company/store/job', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category_id', 'role_id', 'experience', 'education', 'job_type', 'vacancies', 'salary_mode', 'salary_type', 'deadline', 'description', 'apply_on', 'work_time', 'city_id', 'province_id', 'grp_pos_id', 'hard_skill', 'soft_skill', 'benefits', 'show_salary', 'min_salary', 'max_salary']);

        $this->postJson('/api/company/store/job', $this->jobPayload(['category_id' => 999999999, 'city_id' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors(['category_id', 'city_id']);
    }

    public function test_salary_rules(): void
    {
        $this->settings();
        $this->actingAsCompany();

        $this->postJson('/api/company/store/job', $this->jobPayload(['min_salary' => 9000, 'max_salary' => 1000]))
            ->assertStatus(422)->assertJsonValidationErrors(['min_salary', 'max_salary']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['min_salary' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors(['min_salary']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['salary_mode' => 'custom']))
            ->assertStatus(422)->assertJsonValidationErrors(['custom_salary']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['salary_mode' => 'custom', 'custom_salary' => 'Negotiable']))
            ->assertOk()->assertJsonPath('data.data.job.custom_salary', 'Negotiable');
        $this->postJson('/api/company/store/job', $this->jobPayload(['salary_mode' => 'weekly']))
            ->assertStatus(422)->assertJsonValidationErrors(['salary_mode']);
    }

    public function test_apply_channel_rules(): void
    {
        $this->settings();
        $this->actingAsCompany();

        $this->postJson('/api/company/store/job', $this->jobPayload(['apply_on' => 'email']))
            ->assertStatus(422)->assertJsonValidationErrors(['apply_email']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['apply_on' => 'email', 'apply_email' => 'nope']))
            ->assertStatus(422)->assertJsonValidationErrors(['apply_email']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['apply_on' => 'custom_url']))
            ->assertStatus(422)->assertJsonValidationErrors(['apply_url']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['apply_on' => 'fax']))
            ->assertStatus(422)->assertJsonValidationErrors(['apply_on']);

        $this->postJson('/api/company/store/job', $this->jobPayload(['apply_on' => 'custom_url', 'apply_url' => 'https://example.test/apply']))
            ->assertOk()->assertJsonPath('data.data.job.apply_url', 'https://example.test/apply')->assertJsonPath('data.data.job.can_apply', false);
        $this->postJson('/api/company/store/job', $this->jobPayload(['apply_on' => 'email', 'apply_email' => 'hr@example.test']))
            ->assertOk()->assertJsonPath('data.data.job.apply_email', 'hr@example.test');
    }

    public function test_job_order_id_must_be_unique(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $orderId = random_int(100000, 99999999);

        $this->postJson('/api/company/store/job', $this->jobPayload(['job_order_id' => $orderId]))->assertOk();
        $this->postJson('/api/company/store/job', $this->jobPayload(['job_order_id' => $orderId]))
            ->assertStatus(422)->assertJsonValidationErrors(['job_order_id']);
    }

    public function test_featured_badge_consumes_plan_quota(): void
    {
        $this->settings(['featured_job_days' => 7]);
        $this->actingAsCompany();
        $planId = $this->makeUserPlan($this->companyId, featured: 1, highlight: 0);

        $job = $this->postJson('/api/company/store/job', $this->jobPayload(['badge' => 'featured']))
            ->assertOk()->json('data.data.job');

        $this->assertTrue($job['featured']);
        $this->assertSame(now()->addDays(7)->format('Y-m-d'), $job['featured_until']);
        $this->assertSame(0, (int) DB::table('user_plans')->where('id', $planId)->value('featured_job_limit'));

        $this->postJson('/api/company/store/job', $this->jobPayload(['badge' => 'featured']))
            ->assertStatus(400)->assertJson(['error' => 'You have no featured job limit']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['badge' => 'highlight']))
            ->assertStatus(400)->assertJson(['error' => 'You have no highlight job limit']);
        $this->postJson('/api/company/store/job', $this->jobPayload(['badge' => 'gold']))
            ->assertStatus(422)->assertJsonValidationErrors(['badge']);
    }

    public function test_badge_without_plan_is_rejected_and_job_not_created(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $title = 'NoPlan '.$this->companyId;

        $this->postJson('/api/company/store/job', $this->jobPayload(['badge' => 'highlight', 'title' => $title]))
            ->assertStatus(400)->assertJson(['error' => 'You are not authorized to perform this action']);
        $this->assertDatabaseMissing('jobs', ['company_id' => $this->companyId]);
    }
}
