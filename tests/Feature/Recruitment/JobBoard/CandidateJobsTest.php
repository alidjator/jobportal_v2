<?php

namespace Tests\Feature\Recruitment\JobBoard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Recruitment\Domain\JobBoard\JobActivityNotifier;

class CandidateJobsTest extends JobBoardTestCase
{
    public function test_all_candidate_endpoints_require_authentication(): void
    {
        $this->getJson('/api/candidate/jobs')->assertUnauthorized();
        $this->getJson('/api/candidate/jobs/some-slug')->assertUnauthorized();
        $this->getJson('/api/candidate/applied-jobs')->assertUnauthorized();
        $this->getJson('/api/candidate/favorite-jobs')->assertUnauthorized();
        $this->postJson('/api/candidate/jobs/1/bookmark')->assertUnauthorized();
        $this->postJson('/api/candidate/jobs/apply')->assertUnauthorized();
        $this->getJson('/api/candidate/job-alert')->assertUnauthorized();
    }

    public function test_candidate_jobs_returns_plain_list_of_items(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();
        $job = $this->makeJob($company['company'], ['title' => "$m a"]);
        $this->makeJob($company['company'], ['title' => "$m b", 'status' => 'pending']);
        $candidate = $this->makeCandidate();
        DB::table('bookmark_candidate_job')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']]);
        $this->actingAsUser($candidate['user']);

        $data = $this->getJson("/api/candidate/jobs?keyword=$m")->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertTrue(array_is_list($data));
        $this->assertSame([
            'title', 'slug', 'job_details', 'company_name', 'company_logo', 'job_type', 'job_role', 'category', 'country',
            'is_featured', 'is_highlighted', 'deadline', 'salary', 'salary_mode', 'min_salary', 'max_salary', 'bookmarked',
            'allAppliedJobs_count',
        ], array_keys($data[0]));
        $this->assertSame($job['slug'], $data[0]['slug']);
        $this->assertSame($job['role']['id'], $data[0]['job_role']);
        $this->assertTrue($data[0]['bookmarked']);
    }

    public function test_candidate_job_detail_includes_resumes(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company']);
        $candidate = $this->makeCandidate();
        $resume = $this->makeResume($candidate['candidate']);
        $this->actingAsUser($candidate['user']);

        $this->getJson('/api/candidate/jobs/'.$job['slug'])->assertOk()
            ->assertJsonPath('data.job.id', $job['id'])
            ->assertJsonPath('data.resumes.0.id', $resume);

        $this->getJson('/api/candidate/jobs/missing-slug')->assertNotFound();
    }

    public function test_applied_jobs_lists_applications_with_pagination(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $first = $this->makeJob($company['company'], ['salary_mode' => 'custom', 'custom_salary' => 'Negotiable']);
        $second = $this->makeJob($company['company']);
        foreach ([$first, $second] as $job) {
            DB::table('applied_jobs')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id'], 'created_at' => now(), 'updated_at' => now()]);
        }
        // lamaran kandidat lain tidak ikut
        $this->makeCandidate();
        $this->actingAsUser($candidate['user']);

        $response = $this->getJson('/api/candidate/applied-jobs?paginate=1')->assertOk();

        $this->assertSame(2, $response->json('data.total'));
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame($second['slug'], $response->json('data.data.0.slug'));

        $all = $this->getJson('/api/candidate/applied-jobs')->json('data.data');
        $item = collect($all)->firstWhere('slug', $first['slug']);
        $this->assertSame([
            'title', 'slug', 'job_details', 'company_name', 'company_logo', 'job_type', 'job_role', 'country', 'deadline', 'salary',
            'salary_mode', 'min_salary', 'max_salary', 'is_featured', 'is_highlighted', 'is_remote', 'status', 'applied_human_time',
            'applied_at',
        ], array_keys($item));
        $this->assertSame('Negotiable', $item['salary']);
        $this->assertSame($first['role']['name'], $item['job_role']);
        $this->assertSame('active', $item['status']);
        $this->assertSame(now()->format('d M Y h:i A'), $item['applied_at']);
        $this->assertStringContainsString('ago', $item['applied_human_time']);
    }

    public function test_applied_jobs_is_empty_for_user_without_candidate_profile(): void
    {
        $this->actingAsUser($this->makeUser('candidate'));

        $this->getJson('/api/candidate/applied-jobs')->assertOk()->assertJsonPath('data.total', 0)->assertJsonPath('data.data', []);
    }

    public function test_favorite_jobs_uses_resource_collection_shape(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $job = $this->makeJob($company['company']);
        $inactive = $this->makeJob($company['company'], ['status' => 'expired']);
        foreach ([$job, $inactive] as $j) {
            DB::table('bookmark_candidate_job')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $j['id']]);
        }
        $this->actingAsUser($candidate['user']);

        $response = $this->getJson('/api/candidate/favorite-jobs')->assertOk();

        $this->assertSame(['data', 'links', 'meta'], array_keys($response->json('data')));
        $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($response->json('data.links')));
        $this->assertSame(2, $response->json('data.meta.total'));
        $this->assertSame(8, $response->json('data.meta.per_page'));
        // terbaru lebih dulu
        $this->assertSame([$inactive['slug'], $job['slug']], array_column($response->json('data.data'), 'slug'));
        $this->assertTrue($response->json('data.data.0.bookmarked'));
        $this->assertSame($job['category']['id'], $response->json('data.data.1.category'));
    }

    public function test_bookmark_toggles_and_notifies(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($candidate['user']);

        $this->postJson("/api/candidate/jobs/{$job['id']}/bookmark")->assertOk()
            ->assertExactJson(['data' => ['message' => 'Job added to favorite list', 'status' => true]]);

        $this->assertSame(1, DB::table('bookmark_candidate_job')->where(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']])->count());

        $forCompany = DB::table('notifications')->where('notifiable_id', $company['user'])->first();
        $this->assertSame('App\Notifications\Website\Candidate\BookmarkJobNotification', $forCompany->type);
        $this->assertSame('App\Models\User', $forCompany->notifiable_type);
        $payload = json_decode($forCompany->data, true);
        $this->assertSame(['title', 'url', 'title2', 'url2', 'job_id'], array_keys($payload));
        $this->assertSame($job['id'], $payload['job_id']);
        $this->assertStringEndsWith('/job/'.$job['slug'], $payload['url']);
        $this->assertStringEndsWith(' has bookmarked your job', $payload['title']);
        // recent_activities_alert default aktif => kandidat juga diberi tahu
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $candidate['user'])->count());

        $this->postJson("/api/candidate/jobs/{$job['id']}/bookmark")->assertOk()
            ->assertExactJson(['data' => ['message' => 'Job removed from favorite list', 'status' => false]]);
        $this->assertSame(0, DB::table('bookmark_candidate_job')->where(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']])->count());
        // penghapusan tidak membuat notifikasi baru
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $company['user'])->count());
    }

    public function test_bookmark_skips_candidate_notification_when_alerts_disabled(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate(['recent_activities_alert' => 0]);
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($candidate['user']);

        $this->postJson("/api/candidate/jobs/{$job['id']}/bookmark")->assertOk();

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $company['user'])->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $candidate['user'])->count());
    }

    public function test_bookmark_unknown_job_is_404_and_non_candidate_is_403(): void
    {
        $candidate = $this->makeCandidate();
        $this->actingAsUser($candidate['user']);
        $this->postJson('/api/candidate/jobs/999999999/bookmark')->assertNotFound()->assertJson(['error' => 'Job not found']);
        $this->postJson('/api/candidate/jobs/not-a-number/bookmark')->assertNotFound();

        $company = $this->makeCompany();
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($company['user']);
        $this->postJson("/api/candidate/jobs/{$job['id']}/bookmark")->assertForbidden();
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $company['user'])->count());
    }

    public function test_apply_happy_path(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $resume = $this->makeResume($candidate['candidate']);
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($candidate['user']);

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => $job['id'], 'resume_id' => $resume, 'cover_letter' => 'Hire me'])
            ->assertOk()
            ->assertExactJson(['data' => ['message' => 'Your job application submitted successfully!', 'status' => true]]);

        $row = DB::table('applied_jobs')->where(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']])->first();
        $this->assertNotNull($row);
        $this->assertSame('Hire me', $row->cover_letter);
        $this->assertSame($resume, (int) $row->candidate_resume_id);
        // grup default = grup yang tidak bisa dihapus ("No Group")
        $this->assertSame(
            (int) DB::table('application_groups')->where('company_id', $company['company'])->where('is_deleteable', 0)->value('id'),
            (int) $row->application_group_id,
        );

        $forCompany = DB::table('notifications')->where('notifiable_id', $company['user'])->first();
        $this->assertSame('App\Notifications\Website\Candidate\ApplyJobNotification', $forCompany->type);
        $payload = json_decode($forCompany->data, true);
        $this->assertSame(['title', 'url', 'title2', 'url2'], array_keys($payload));
        $this->assertStringEndsWith(' has applied your job', $payload['title']);
        $this->assertStringStartsWith('You have applied the job of ', $payload['title2']);
        $this->assertStringEndsWith('/company/my-jobs', $payload['url']);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $candidate['user'])->count());
    }

    public function test_apply_sends_notifications_through_the_interface(): void
    {
        $fake = new class implements JobActivityNotifier
        {
            public array $applied = [];

            public function applied(array $candidateUser, array $job): void
            {
                $this->applied[] = [$candidateUser['id'], $job['id']];
            }

            public function bookmarked(array $candidateUser, array $job): void {}
        };
        $this->app->instance(JobActivityNotifier::class, $fake);

        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $resume = $this->makeResume($candidate['candidate']);
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($candidate['user']);

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => $job['id'], 'resume_id' => $resume, 'cover_letter' => 'x'])->assertOk();

        $this->assertSame([[$candidate['user'], $job['id']]], $fake->applied);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $company['user'])->count());
    }

    public function test_database_notifier_is_best_effort(): void
    {
        $notifier = $this->app->make(JobActivityNotifier::class);
        $user = ['id' => 1, 'name' => 'x', 'role' => 'candidate', 'recent_activities_alert' => false];
        // id penerima tidak valid => insert gagal, tetapi tidak boleh melempar
        $job = ['id' => 1, 'title' => 't', 'slug' => 's', 'company_user_id' => 'not-an-id', 'company_user_name' => 'c'];

        $notifier->applied($user, $job);
        $notifier->bookmarked($user, $job);

        $this->assertTrue(true);
    }

    public function test_apply_validation(): void
    {
        $this->actingAsUser($this->makeCandidate()['user']);

        $this->postJson('/api/candidate/jobs/apply', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id', 'resume_id', 'cover_letter'])
            ->assertJsonPath('errors.resume_id.0', 'Please select resume')
            ->assertJsonPath('errors.cover_letter.0', 'Please enter cover letter');

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => 999999999, 'resume_id' => 1, 'cover_letter' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['job_id']);

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => 1, 'resume_id' => 1, 'cover_letter' => str_repeat('a', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['cover_letter']);
    }

    public function test_apply_rejects_resume_that_is_not_the_candidates(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $someoneElsesResume = $this->makeResume($this->makeCandidate()['candidate']);
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($candidate['user']);

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => $job['id'], 'resume_id' => $someoneElsesResume, 'cover_letter' => 'x'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'You can not apply on this job. Because this resume is not yours']);

        $this->assertSame(0, DB::table('applied_jobs')->where('job_id', $job['id'])->count());
    }

    public function test_apply_rejects_jobs_not_open_for_website_applications(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $resume = $this->makeResume($candidate['candidate']);
        $job = $this->makeJob($company['company'], ['apply_on' => 'email']);
        $this->actingAsUser($candidate['user']);

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => $job['id'], 'resume_id' => $resume, 'cover_letter' => 'x'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'You can not apply on this job. Because this job is not for apply on website']);
    }

    public function test_apply_twice_to_same_job_is_rejected(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $resume = $this->makeResume($candidate['candidate']);
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($candidate['user']);
        $payload = ['job_id' => $job['id'], 'resume_id' => $resume, 'cover_letter' => 'x'];

        $this->postJson('/api/candidate/jobs/apply', $payload)->assertOk();
        $this->postJson('/api/candidate/jobs/apply', $payload)->assertStatus(400)
            ->assertExactJson(['error' => 'You have already applied for this job']);

        $this->assertSame(1, DB::table('applied_jobs')->where(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']])->count());
    }

    public function test_apply_by_non_candidate_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company']);
        $this->actingAsUser($company['user']);

        $this->postJson('/api/candidate/jobs/apply', ['job_id' => $job['id'], 'resume_id' => 1, 'cover_letter' => 'x'])->assertForbidden();
    }

    public function test_job_alert_lists_related_job_notifications(): void
    {
        $company = $this->makeCompany();
        $candidate = $this->makeCandidate();
        $job = $this->makeJob($company['company'], ['salary_mode' => 'custom', 'custom_salary' => 'Negotiable']);
        $related = 'App\Notifications\Website\Candidate\RelatedJobNotification';
        $insert = fn (string $type, array $data, int $user, string $at) => DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => $type, 'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $user, 'data' => json_encode($data), 'created_at' => $at, 'updated_at' => $at,
        ]);
        $insert($related, ['job_id' => $job['id'], 'title' => 'x'], $candidate['user'], now()->subMinute()->toDateTimeString());
        $insert($related, ['job_id' => 999999999], $candidate['user'], now()->subMinutes(2)->toDateTimeString());
        $insert('Other\Notification', ['job_id' => $job['id']], $candidate['user'], now()->toDateTimeString());
        $insert($related, ['job_id' => $job['id']], $this->makeCandidate()['user'], now()->toDateTimeString());
        $this->actingAsUser($candidate['user']);

        $response = $this->getJson('/api/candidate/job-alert')->assertOk();

        $this->assertSame('Job alert retried successful!', $response->json('data.message'));
        $this->assertSame(2, $response->json('data.notifications.total'));
        $this->assertSame(8, $response->json('data.notifications.per_page'));
        $items = $response->json('data.notifications.data');
        $this->assertSame([
            'title', 'slug', 'job_type', 'salary', 'deadline', 'country', 'company_name', 'company_logo',
        ], array_keys($items[0]));
        $this->assertSame($job['slug'], $items[0]['slug']);
        $this->assertSame('Negotiable', $items[0]['salary']);
        $this->assertSame($job['type']['name'], $items[0]['job_type']);
        $this->assertNull($items[1]);

        $this->getJson('/api/candidate/job-alert?paginate=1')->assertJsonCount(1, 'data.notifications.data');
    }
}
