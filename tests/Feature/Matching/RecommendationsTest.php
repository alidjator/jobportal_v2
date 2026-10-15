<?php

namespace Tests\Feature\Matching;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Matching\Application\RecommendationQueue;
use Src\Matching\Application\SendRecommendationBatch;
use Src\Matching\Infrastructure\Jobs\SendRecommendationBatchJob;
use Src\Matching\Infrastructure\Mail\RecommendationMail;
use Src\Shared\Application\Matching\CandidateSignalSource;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * The matching engine through its four surfaces: recommended jobs, the match on the public job list and page, recommended candidates and the weekly email.
 * The fixture job seeker (Siti) has a complete profile in Bandung, 28 months of experience as a Backend Developer, a headline "Backend developer" and, here, the skill PHP.
 */
final class RecommendationsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $php;

    private int $laravel;

    /** @var array{province_id: int, city_id: int, subdistrict_id: int, village_id: int, postal_code_id: int} */
    private array $here;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->php = $this->skillId('php');
        $this->laravel = $this->skillId('laravel');
        $this->here = $this->locationChain();
        $this->api('PUT', '/me/skills', ['skill_ids' => [$this->php]], $this->seekerToken)->assertOk();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /**
     * A live job assigned to the first recruiter.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $skills
     */
    private function job(string $title = 'Backend Developer', array $attributes = [], array $skills = []): int
    {
        $id = $this->insertJob($title, 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31', 'published_at' => now(), ...$attributes]);

        foreach ($skills as $skill) {
            DB::table('job_job_skills')->insert(['job_id' => $id, 'skill_id' => $skill, 'created_at' => now()]);
        }

        return $id;
    }

    /**
     * The job that fits Siti at 75%: her city, a 1 to 3 years level, the role Backend Developer and two skills of which she has one.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fittingJob(string $title = 'Backend Developer', array $attributes = []): int
    {
        return $this->job($title, [
            'province_id' => $this->here['province_id'], 'city_id' => $this->here['city_id'],
            'experience_level_id' => $this->referenceId('reference_experience_levels', 'one-to-three-years', ['min_years' => 1, 'max_years' => 3]),
            'role_id' => $this->referenceId('reference_job_roles', 'backend-developer'),
            ...$attributes,
        ], [$this->php, $this->laravel]);
    }

    /** A remote job that fits Siti at 100%: one skill she has, and the word "Backend" in the title. */
    private function remoteJob(string $title = 'Backend Engineer'): int
    {
        return $this->job($title, ['work_mode' => 'remote'], [$this->php]);
    }

    private function otherProvince(): int
    {
        return (int) (DB::table('reference_provinces')->where('bps_code', '31')->value('id') ?? DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']));
    }

    private function otherCity(): int
    {
        return (int) (DB::table('reference_cities')->where('bps_code', '3274')->value('id')
            ?? DB::table('reference_cities')->insertGetId(['province_id' => $this->here['province_id'], 'bps_code' => '3274', 'name' => 'CIMAHI', 'type' => 'city']));
    }

    /**
     * A job seeker who can be found, with the given skills.
     *
     * @param  list<int>  $skills
     * @return array{id: int, slug: string, token: string, resume: int}
     */
    private function candidate(string $email, string $name, array $skills = [], bool $discoverable = true): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $resume = $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => $discoverable]);

        if ($skills !== []) {
            $this->api('PUT', '/me/skills', ['skill_ids' => $skills], $token)->assertOk();
        }

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token, 'resume' => $resume];
    }

    private function invite(int $job, int $userId): void
    {
        DB::table('jobinvitation_invitations')->insert([
            'company_id' => $this->companyId, 'job_id' => $job, 'user_id' => $userId, 'invited_by_user_id' => $this->adminId, 'status' => 'sent',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function anonymous(string $uri): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->getJson('/api/v1'.$uri);
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return array_map('intval', array_column($response->json('data'), 'id'));
    }

    private function runEmails(): int
    {
        $before = Mail::queued(RecommendationMail::class)->count();

        $this->assertSame(0, Artisan::call('matching:send-recommendations'));

        return Mail::queued(RecommendationMail::class)->count() - $before;
    }

    private function queriesOfRun(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(0, Artisan::call('matching:send-recommendations'));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // ----- recommended jobs (J8) -----

    public function test_the_recommended_jobs_are_the_live_jobs_that_fit_best_at_forty_percent_or_more(): void
    {
        $fitting = $this->fittingJob();
        $remote = $this->remoteJob();
        // The same province but another city, ten years asked, a skill she lacks and a title with nothing in common: 17%, under the threshold.
        $this->job('Cook', [
            'province_id' => $this->here['province_id'], 'city_id' => $this->otherCity(),
            'experience_level_id' => $this->referenceId('reference_experience_levels', 'ten-years', ['min_years' => 10, 'max_years' => null]),
        ], [$this->laravel]);
        // Another province and not remote: not even looked at.
        $this->fittingJob('Backend Developer Jakarta', ['province_id' => $this->otherProvince(), 'city_id' => null]);

        $response = $this->api('GET', '/me/recommendations', token: $this->seekerToken)->assertOk();

        $this->assertSame([$remote, $fitting], $this->ids($response));
        $this->assertSame(100, $response->json('data.0.match.percent'));
        $this->assertSame(['skills' => 100, 'location' => 100, 'experience' => null, 'education' => null, 'salary' => null, 'role' => 100], $response->json('data.0.match.signals'));
        $this->assertSame(75, $response->json('data.1.match.percent'));
        $this->assertSame(['skills' => 50, 'location' => 100, 'experience' => 100, 'education' => null, 'salary' => null, 'role' => 100], $response->json('data.1.match.signals'));
        // The row of the public search, plus the match.
        $this->assertSame('Backend Developer', $response->json('data.1.title'));
        $this->assertSame('PT-MAJU', $response->json('data.1.company.name'));
        $this->assertSame(['id', 'slug', 'title', 'company', 'location', 'job_type', 'job_level', 'work_mode', 'salary', 'deadline', 'published_at', 'match', 'saved'], array_keys($response->json('data.1')));
    }

    public function test_jobs_already_applied_to_or_invited_to_are_left_out(): void
    {
        $kept = $this->fittingJob();
        $applied = $this->fittingJob('Backend Developer Applied');
        $invited = $this->fittingJob('Backend Developer Invited');
        $this->apply($applied)->assertCreated();
        $this->invite($invited, $this->seekerId);

        $this->assertSame([$kept], $this->ids($this->api('GET', '/me/recommendations', token: $this->seekerToken)->assertOk()));
    }

    public function test_the_list_holds_at_most_twenty_jobs(): void
    {
        for ($i = 1; $i <= 22; $i++) {
            $this->remoteJob("Backend Engineer {$i}");
        }

        $this->assertCount(20, $this->api('GET', '/me/recommendations', token: $this->seekerToken)->assertOk()->json('data'));
    }

    public function test_a_job_seeker_without_a_complete_profile_gets_no_recommendations(): void
    {
        $this->remoteJob();
        $bare = $this->tokenFor($this->seeker('bare@example.test'));

        $this->api('GET', '/me/recommendations', token: $bare)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_only_a_signed_in_job_seeker_gets_recommendations(): void
    {
        $this->anonymous('/me/recommendations')->assertStatus(401);
        $this->api('GET', '/me/recommendations', token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
    }

    // ----- the match on the public list and page -----

    public function test_a_signed_in_job_seeker_sees_the_match_on_the_job_list_and_the_job_page(): void
    {
        $fitting = $this->fittingJob();
        $remote = $this->remoteJob();

        $list = $this->api('GET', '/jobs', token: $this->seekerToken)->assertOk();
        $this->assertEquals([$fitting => 75, $remote => 100], collect($list->json('data'))->mapWithKeys(fn (array $row): array => [$row['id'] => $row['match']['percent']])->all());
        $this->assertSame(['percent' => 75], collect($list->json('data'))->firstWhere('id', $fitting)['match']);

        $page = $this->api('GET', "/jobs/{$fitting}", token: $this->seekerToken)->assertOk();
        $this->assertSame(75, $page->json('data.match.percent'));
        $this->assertSame(['skills' => 50, 'location' => 100, 'experience' => 100, 'education' => null, 'salary' => null, 'role' => 100], $page->json('data.match.signals'));
    }

    public function test_the_best_match_order_ranks_by_percentage_then_newest_and_says_which_order_was_used(): void
    {
        $fitting = $this->fittingJob('Backend Developer', ['published_at' => '2026-10-04 08:00:00']);
        $remote = $this->remoteJob();
        $older = $this->fittingJob('Backend Developer Two', ['published_at' => '2026-10-01 08:00:00']);
        $unknown = $this->job('Chef', ['published_at' => '2026-10-04 09:00:00']);

        $response = $this->api('GET', '/jobs?sort=match', token: $this->seekerToken)->assertOk()->assertJsonPath('meta.sort', 'match');

        $this->assertSame([$remote, $fitting, $older], array_slice($this->ids($response), 0, 3));
        $this->assertSame($unknown, $this->ids($response)[3], 'a job without a percentage comes last');
        $this->assertSame(100, $response->json('data.0.match.percent'));
        $this->assertSame(4, $response->json('meta.total'));
    }

    public function test_the_best_match_order_falls_back_without_a_complete_profile_or_a_sign_in(): void
    {
        $this->remoteJob('Backend Engineer');
        $this->job('Chef', ['published_at' => '2026-10-04 09:00:00']);
        $bare = $this->tokenFor($this->seeker('bare@example.test'));

        $this->api('GET', '/jobs?sort=match', token: $bare)->assertOk()->assertJsonPath('meta.sort', '-published_at');
        $this->anonymous('/jobs?sort=match')->assertOk()->assertJsonPath('meta.sort', '-published_at');
        $this->api('GET', '/jobs?sort=match&filter[q]=backend', token: $bare)->assertOk()->assertJsonPath('meta.sort', 'relevance');
    }

    public function test_the_match_is_left_out_for_everyone_else_and_the_cache_headers_follow(): void
    {
        $job = $this->fittingJob();
        $bare = $this->tokenFor($this->seeker('bare@example.test'));

        foreach (['/jobs', "/jobs/{$job}"] as $uri) {
            $public = $this->anonymous($uri)->assertOk();
            $this->assertStringContainsString('public', (string) $public->headers->get('Cache-Control'));
            $this->assertStringContainsString('Authorization', (string) $public->headers->get('Vary'));
            $this->assertNotNull($public->headers->get('ETag'));
            $this->assertStringNotContainsString('"match"', $public->getContent());

            $seeker = $this->api('GET', $uri, token: $this->seekerToken)->assertOk();
            $this->assertStringContainsString('private', (string) $seeker->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $seeker->headers->get('Cache-Control'));
            $this->assertStringContainsString('Authorization', (string) $seeker->headers->get('Vary'));
            $this->assertStringContainsString('"match"', $seeker->getContent());

            // A company member, a job seeker with an incomplete profile and a token that is not valid get the public answer, without the match.
            foreach ([$this->adminToken, $bare, 'not-a-token'] as $token) {
                $other = $this->api('GET', $uri, token: $token)->assertOk();
                $this->assertStringNotContainsString('"match"', $other->getContent());
            }
        }
    }

    public function test_the_match_is_null_when_the_job_says_too_little(): void
    {
        // Only the title says anything: a location and a role are worth 25 together, under the 40 needed.
        $job = $this->job('Backend Developer', ['province_id' => $this->here['province_id']]);

        $page = $this->api('GET', "/jobs/{$job}", token: $this->seekerToken)->assertOk();

        $this->assertNull($page->json('data.match.percent'));
        $this->assertSame(50, $page->json('data.match.signals.location'));
        $this->assertSame([], $this->ids($this->api('GET', '/me/recommendations', token: $this->seekerToken)));
    }

    public function test_the_jobs_of_an_alert_carry_the_match_too(): void
    {
        $job = $this->remoteJob();
        $alert = (int) $this->api('POST', '/me/job-alerts', ['name' => 'Remote', 'frequency' => 'daily', 'criteria' => ['work_mode' => ['remote']]], $this->seekerToken)->assertCreated()->json('data.id');

        $response = $this->api('GET', "/me/job-alerts/{$alert}/jobs", token: $this->seekerToken)->assertOk();

        $this->assertSame([$job], $this->ids($response));
        $this->assertSame(['percent' => 100], $response->json('data.0.match'));
    }

    // ----- recommended candidates (R12) -----

    public function test_the_recommended_candidates_are_the_findable_ones_that_fit_the_job_best(): void
    {
        $job = $this->fittingJob();
        $ana = $this->candidate('ana@example.test', 'Ana Putri', [$this->php, $this->laravel]);
        $budi = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $eko = $this->candidate('eko@example.test', 'Eko Prasetyo', [$this->php]);
        $this->candidate('cara@example.test', 'Cara Dewi', [$this->php, $this->laravel], discoverable: false);
        $dewi = $this->candidate('dewi@example.test', 'Dewi Lestari', [$this->php, $this->laravel]);
        $this->apply($job, ['resume_id' => $dewi['resume']], $dewi['token'])->assertCreated();
        $this->invite($job, $eko['id']);

        $response = $this->api('GET', "/company/jobs/{$job}/candidates", token: $this->recruiterToken)->assertOk();

        $this->assertSame([$ana['slug'], $eko['slug'], $budi['slug']], array_column($response->json('data'), 'slug'));
        $this->assertSame([100, 75, 50], array_column(array_column($response->json('data'), 'match'), 'percent'));
        $this->assertSame([false, true, false], array_column($response->json('data'), 'invited'));
        $this->assertSame(['skills' => 100, 'location' => 100, 'experience' => 100, 'education' => null, 'salary' => null, 'role' => 100], $response->json('data.0.match.signals'));
        // The row of the candidate search, plus the match: no contact data, no protected attribute.
        $this->assertSame(
            ['slug', 'name', 'has_avatar', 'headline', 'province', 'city', 'experience_level', 'availability', 'highest_education', 'updated_at', 'match', 'invited'],
            array_keys($response->json('data.0')),
        );
        $this->assertSame('Ana Putri', $response->json('data.0.name'));
        $this->assertSame(0, DB::table('jobseeker_profile_views')->count(), 'Listing recommendations logs no profile view.');

        // A company admin sees the same list.
        $this->assertSame($response->json('data'), $this->api('GET', "/company/jobs/{$job}/candidates", token: $this->adminToken)->json('data'));
    }

    public function test_candidates_who_fit_too_little_are_not_recommended(): void
    {
        // Ten years asked and two skills the candidates lack: 29%.
        $job = $this->job('Cook', [
            'province_id' => $this->here['province_id'], 'city_id' => $this->here['city_id'],
            'experience_level_id' => $this->referenceId('reference_experience_levels', 'ten-years', ['min_years' => 10, 'max_years' => null]),
        ], [$this->php, $this->laravel]);
        $this->candidate('ana@example.test', 'Ana Putri');

        $this->api('GET', "/company/jobs/{$job}/candidates", token: $this->recruiterToken)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_a_remote_job_looks_for_candidates_in_every_province(): void
    {
        $job = $this->remoteJob();
        $ana = $this->candidate('ana@example.test', 'Ana Putri', [$this->php]);
        DB::table('jobseeker_profiles')->where('user_id', $ana['id'])->update(['province_id' => $this->otherProvince(), 'city_id' => null]);
        // Without a city Ana's profile is not complete any more, so she drops out; Budi lives elsewhere but keeps a city of his own.
        $city = (int) DB::table('reference_cities')->insertGetId(['province_id' => $this->otherProvince(), 'bps_code' => '3171', 'name' => 'JAKARTA PUSAT', 'type' => 'city']);
        $budi = $this->candidate('budi.s@example.test', 'Budi Santoso', [$this->php]);
        DB::table('jobseeker_profiles')->where('user_id', $budi['id'])->update(['province_id' => $this->otherProvince(), 'city_id' => $city]);

        $this->assertSame([$budi['slug']], array_column($this->api('GET', "/company/jobs/{$job}/candidates", token: $this->recruiterToken)->assertOk()->json('data'), 'slug'));
    }

    public function test_the_recommended_candidates_need_a_live_job_of_the_callers_own(): void
    {
        $job = $this->fittingJob();
        $draft = $this->insertJob('Draft', 'draft', [$this->recruiterId]);

        $this->api('GET', "/company/jobs/{$draft}/candidates", token: $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:job-not-live');
        // A recruiter the job is not assigned to, another company's admin and a missing job all get 404.
        $this->api('GET', "/company/jobs/{$job}/candidates", token: $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$job}/candidates", token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/jobs/999999/candidates', token: $this->adminToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$job}/candidates", token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->anonymous("/company/jobs/{$job}/candidates")->assertStatus(401);
    }

    // ----- the weekly email -----

    public function test_the_weekly_email_names_the_best_jobs_once_and_only_new_ones_later(): void
    {
        Mail::fake();
        $this->fittingJob();
        $this->remoteJob();

        $this->assertSame(1, $this->runEmails());

        Mail::assertQueued(RecommendationMail::class, 1);
        Mail::assertQueued(RecommendationMail::class, function (RecommendationMail $mail): bool {
            $mail->assertSeeInText('Hello Siti Rahma');
            $mail->assertSeeInText('these 2 jobs');
            $mail->assertSeeInText('Backend Engineer (100% match)');
            $mail->assertSeeInText('Backend Developer (75% match)');

            return $mail->hasTo('siti@example.test') && $mail->queue === 'bulk';
        });
        $this->assertSame(2, DB::table('matching_deliveries')->where('user_id', $this->seekerId)->count());

        // The same day, and a week later with nothing new, nothing goes out.
        $this->assertSame(0, $this->runEmails());
        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->assertSame(0, $this->runEmails());
        Mail::assertQueued(RecommendationMail::class, 1);

        $new = $this->remoteJob('Backend Engineer Remote');
        $this->assertSame(1, $this->runEmails());
        Mail::assertQueued(RecommendationMail::class, 2);
        $second = Mail::queued(RecommendationMail::class)->last();
        $second->assertSeeInText('these 1 jobs');
        $second->assertSeeInText('Backend Engineer Remote (96% match)');
        $second->assertDontSeeInText('Backend Developer (75% match)');
        $this->assertSame(3, DB::table('matching_deliveries')->where('user_id', $this->seekerId)->count());
        $this->assertTrue(DB::table('matching_deliveries')->where('job_id', $new)->exists());
    }

    public function test_an_email_names_at_most_five_jobs_and_the_rest_follow_a_week_later(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 7; $i++) {
            $this->remoteJob("Backend Engineer {$i}");
        }

        $this->assertSame(1, $this->runEmails());
        $this->assertSame(5, DB::table('matching_deliveries')->count());

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->assertSame(1, $this->runEmails());
        $this->assertSame(7, DB::table('matching_deliveries')->count());
        Mail::assertQueued(RecommendationMail::class, 2);
    }

    public function test_jobs_applied_to_or_invited_to_and_jobs_under_the_threshold_are_not_emailed(): void
    {
        Mail::fake();
        $this->assertSame(0, $this->runEmails(), 'No job at all.');

        $applied = $this->remoteJob('Backend Engineer Applied');
        $invited = $this->remoteJob('Backend Engineer Invited');
        $this->job('Cook', ['province_id' => $this->here['province_id'], 'city_id' => $this->otherCity()], [$this->laravel]);
        $this->apply($applied)->assertCreated();
        $this->invite($invited, $this->seekerId);

        $this->assertSame(0, $this->runEmails());
        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('matching_deliveries')->count());
    }

    public function test_nothing_is_sent_to_a_person_who_cannot_or_does_not_want_it(): void
    {
        Mail::fake();
        $this->remoteJob();

        $this->api('PUT', '/me/notification-preferences/recommendation/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $this->assertSame(0, $this->runEmails());

        $this->api('PUT', '/me/notification-preferences/recommendation/email', ['enabled' => true], $this->seekerToken)->assertNoContent();
        DB::table('identity_users')->where('id', $this->seekerId)->update(['email_verified_at' => null]);
        $this->assertSame(0, $this->runEmails());

        DB::table('identity_users')->where('id', $this->seekerId)->update(['email_verified_at' => '2026-10-01 00:00:00', 'status' => 'suspended']);
        $this->assertSame(0, $this->runEmails());

        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('matching_deliveries')->count());

        DB::table('identity_users')->where('id', $this->seekerId)->update(['status' => 'active']);
        $this->assertSame(1, $this->runEmails());
    }

    public function test_the_email_is_in_the_language_of_the_recipient(): void
    {
        Mail::fake();
        $this->remoteJob();
        DB::table('identity_users')->where('id', $this->seekerId)->update(['locale' => 'id']);

        $this->assertSame(1, $this->runEmails());

        Mail::assertQueued(RecommendationMail::class, function (RecommendationMail $mail): bool {
            $mail->assertSeeInText('Berdasarkan profil Anda, 1 lowongan ini mungkin cocok');

            return true;
        });
    }

    public function test_the_command_runs_every_monday_at_eight_in_jakarta(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'matching:send-recommendations'));

        $this->assertCount(1, $events);
        $zone = $events->first()->timezone;
        $this->assertSame(['0 8 * * 1', 'Asia/Jakarta'], [$events->first()->expression, is_string($zone) ? $zone : $zone?->getName()]);
    }

    public function test_the_command_only_queues_the_first_batch_on_the_bulk_queue(): void
    {
        Queue::fake();

        $this->assertSame(0, Artisan::call('matching:send-recommendations'));

        Queue::assertPushed(SendRecommendationBatchJob::class, 1);
        Queue::assertPushed(SendRecommendationBatchJob::class, fn (SendRecommendationBatchJob $job): bool => $job->afterId === 0 && $job->queue === 'bulk');
    }

    public function test_a_full_batch_queues_the_next_one_and_a_short_one_ends_the_run(): void
    {
        $this->app->instance(CandidateSignalSource::class, new class implements CandidateSignalSource
        {
            public function forUsers(array $userIds): array
            {
                return [];
            }

            public function userIdsAfter(int $afterId, int $limit): array
            {
                return $afterId === 0 ? range(1, $limit) : [$afterId + 1];
            }
        });
        $queue = Mockery::mock(RecommendationQueue::class);
        $queue->shouldReceive('dispatchBatch')->once()->with(SendRecommendationBatch::BATCH);

        $this->app->call((new SendRecommendationBatchJob(0))->handle(...), ['queue' => $queue]);
        $this->app->call((new SendRecommendationBatchJob(SendRecommendationBatch::BATCH))->handle(...), ['queue' => $queue]);
    }

    public function test_the_queries_of_a_batch_do_not_grow_with_the_number_of_job_seekers(): void
    {
        Mail::fake();
        $this->remoteJob();

        $alone = $this->queriesOfRun();
        $this->assertSame(1, DB::table('matching_deliveries')->distinct()->count('user_id'));
        DB::table('matching_deliveries')->delete();

        foreach (range(1, 4) as $n) {
            $this->candidate("extra{$n}@example.test", "Extra {$n}", [$this->php]);
        }

        $together = $this->queriesOfRun();

        $this->assertSame(5, DB::table('matching_deliveries')->distinct()->count('user_id'));
        $this->assertLessThanOrEqual($alone + 4, $together, 'Only one insert per extra email may be added.');
    }

    // ----- erasure -----

    public function test_anonymising_an_account_forgets_what_was_emailed_to_it(): void
    {
        Mail::fake();
        $this->remoteJob();
        $other = $this->candidate('other@example.test', 'Other Person', [$this->php]);
        $this->assertSame(2, $this->runEmails());
        $this->assertSame(2, DB::table('matching_deliveries')->count());

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $this->assertSame([$other['id']], DB::table('matching_deliveries')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
    }
}
