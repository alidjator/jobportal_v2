<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Job\Application\JobSettings;
use Src\Job\Application\Reports\EraseJobReports;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 1: a job seeker reports a job, enough reports hold it, an admin decides. */
final class JobReportTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private string $opsToken;

    private string $secondToken;

    private string $thirdToken;

    private int $secondId;

    private int $thirdId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');

        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['jobs.manage', 'dashboard.view']);
        $this->opsToken = $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;

        $this->secondId = $this->seeker('bagus@example.test', name: 'Bagus Santoso');
        $this->secondToken = $this->tokenFor($this->secondId);
        $this->thirdId = $this->seeker('wati@example.test', name: 'Wati Lestari');
        $this->thirdToken = $this->tokenFor($this->thirdId);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $body */
    private function report(int $job, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', "/jobs/{$job}/reports", ['reason' => 'fee_requested', ...$body], $token ?? $this->seekerToken);
    }

    private function admin(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->api($method, '/admin'.$uri, $body, $this->opsToken);
    }

    private function publicJob(int $job): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->getJson("/api/v1/jobs/{$job}");
    }

    /** @return list<int> */
    private function searchIds(): array
    {
        $this->app['auth']->forgetGuards();

        return array_map('intval', $this->flushHeaders()->getJson('/api/v1/jobs')->assertOk()->json('data.*.id'));
    }

    /** All three seekers report the job, so it goes on hold. */
    private function holdByThree(int $job): void
    {
        foreach ([$this->seekerToken, $this->secondToken, $this->thirdToken] as $token) {
            $this->report($job, token: $token)->assertCreated();
        }
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function notificationsOf(int $userId): array
    {
        return array_map(
            fn ($row): array => ['type' => (string) $row->type, 'data' => json_decode((string) $row->data, true)],
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }

    // ----- reporting -----

    public function test_a_job_seeker_reports_a_live_job(): void
    {
        $job = $this->liveJob();

        $this->report($job, ['reason' => 'fake_or_misleading', 'note' => '  They asked me to pay first.  '])
            ->assertCreated()
            ->assertJsonPath('data.job_id', $job)
            ->assertJsonPath('data.reason', 'fake_or_misleading')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.created_at', '2026-10-05T09:00:00+00:00');

        $row = DB::table('job_reports')->first();
        $this->assertSame([$job, $this->seekerId, 'fake_or_misleading', 'They asked me to pay first.', 'open'], [(int) $row->job_id, (int) $row->reporter_user_id, $row->reason, $row->note, $row->status]);
        $this->assertNull($this->jobRow($job)->report_hold_at, 'one report is not enough for a hold');
    }

    public function test_only_a_job_seeker_with_a_verified_email_reports_a_live_job(): void
    {
        $job = $this->liveJob();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson("/api/v1/jobs/{$job}/reports", ['reason' => 'spam_or_duplicate'])->assertUnauthorized();

        $this->report($job, token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');

        $unverified = $this->tokenFor($this->seeker('belum@example.test', null));
        $this->report($job, token: $unverified)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:email-not-verified');

        $draft = $this->insertJob('Draft job', 'draft');
        $this->report($draft)->assertNotFound();
        $this->report($job + 9999)->assertNotFound();

        $this->assertSame(0, DB::table('job_reports')->count());
    }

    public function test_a_reason_must_be_one_of_the_list_and_other_needs_a_note(): void
    {
        $job = $this->liveJob();

        $this->report($job, ['reason' => 'because'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:validation-failed');
        $this->report($job, ['reason' => 'other'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-report')->assertJsonPath('errors.note.0', 'Tell us what is wrong with this job.');
        $this->report($job, ['reason' => 'other', 'note' => '   '])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-report');
        $this->report($job, ['note' => str_repeat('a', 1001)])->assertStatus(422);

        $this->report($job, ['reason' => 'other', 'note' => 'The salary is not what the page says.'])->assertCreated();
        $this->assertSame(1, DB::table('job_reports')->count());
    }

    public function test_one_open_report_per_reporter_and_job(): void
    {
        $job = $this->liveJob();
        $other = $this->liveJob();

        $this->report($job)->assertCreated();
        $this->report($job, ['reason' => 'spam_or_duplicate'])->assertConflict()->assertJsonPath('type', 'urn:jobportal:error:already-reported');
        $this->report($other)->assertCreated();
        $this->report($job, token: $this->secondToken)->assertCreated();

        $this->assertSame(3, DB::table('job_reports')->count());
    }

    public function test_a_reporter_is_limited_to_ten_reports_an_hour(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->report($this->liveJob())->assertCreated();
        }

        $this->report($this->liveJob())->assertStatus(429);
    }

    // ----- the hold -----

    public function test_three_different_reporters_put_the_job_on_hold(): void
    {
        $job = $this->liveJob();

        $this->report($job)->assertCreated();
        $this->report($job, token: $this->secondToken)->assertCreated();
        $this->assertNull($this->jobRow($job)->report_hold_at);
        $this->assertContains($job, $this->searchIds());

        $this->report($job, token: $this->thirdToken)->assertCreated();

        $this->assertSame('2026-10-05 09:00:00', $this->jobRow($job)->report_hold_at);
        $this->assertSame('active', $this->jobRow($job)->status, 'the status does not change');
        $this->assertNotContains($job, $this->searchIds());
        $this->publicJob($job)->assertNotFound();
    }

    public function test_a_held_job_takes_no_applications_but_existing_ones_go_on(): void
    {
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        $this->holdByThree($job);

        $this->apply($job, token: $this->secondToken)->assertConflict()->assertJsonPath('type', 'urn:jobportal:error:job-on-hold');
        $this->assertSame(1, DB::table('recruitment_applications')->where('job_id', $job)->count());
        $this->assertSame(200, $this->api('GET', "/company/jobs/{$job}", token: $this->adminToken)->status());
    }

    public function test_the_company_sees_the_hold_and_the_assignees_are_told_without_the_reasons(): void
    {
        $job = $this->liveJob();
        $this->holdByThree($job);

        $this->api('GET', "/company/jobs/{$job}", token: $this->adminToken)->assertOk()->assertJsonPath('data.report_hold_at', '2026-10-05T09:00:00+00:00');

        $notices = $this->notificationsOf($this->adminId);
        $this->assertSame([['type' => 'job.held', 'data' => ['job_id' => $job]]], $notices);
        $this->assertStringNotContainsString('fee_requested', json_encode($notices, JSON_THROW_ON_ERROR));
    }

    public function test_a_held_job_cannot_be_reported_again_and_keeps_its_first_hold_time(): void
    {
        $job = $this->liveJob();
        $this->holdByThree($job);
        DB::table('job_jobs')->where('id', $job)->update(['report_hold_at' => '2026-10-01 00:00:00']);

        $fourth = $this->tokenFor($this->seeker('keempat@example.test'));
        $this->report($job, token: $fourth)->assertNotFound();

        $this->assertSame('2026-10-01 00:00:00', $this->jobRow($job)->report_hold_at);
    }

    public function test_a_threshold_of_zero_turns_holds_off(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(JobSettings::REPORT_HOLD_THRESHOLD, 0, null);

        $job = $this->liveJob();
        $this->holdByThree($job);

        $this->assertNull($this->jobRow($job)->report_hold_at);
        $this->assertContains($job, $this->searchIds());
        $this->assertSame(3, DB::table('job_reports')->count());
    }

    // ----- the admin -----

    public function test_every_admin_report_route_needs_the_jobs_permission(): void
    {
        $job = $this->liveJob();
        $weak = AdminModel::query()->create(['name' => 'Weak', 'email' => 'weak@example.test', 'password' => 'hash', 'status' => 'active']);
        $weak->givePermissionTo(['faqs.manage']);
        $weakToken = $weak->createToken('console', ['*'], now()->addDay())->plainTextToken;

        foreach ([['GET', '/admin/job-reports'], ['GET', "/admin/jobs/{$job}/reports"], ['POST', "/admin/jobs/{$job}/reports/dismiss"]] as [$method, $uri]) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders()->json($method, '/api/v1'.$uri)->assertUnauthorized();
            $this->api($method, $uri, [], $weakToken)->assertForbidden();
            $this->api($method, $uri, [], $this->seekerToken)->assertUnauthorized();
        }
    }

    public function test_the_admin_list_puts_held_jobs_first_then_the_most_reported(): void
    {
        $few = $this->liveJob();
        $many = $this->liveJob();
        $held = $this->liveJob();
        $none = $this->liveJob();

        $this->report($few, ['reason' => 'spam_or_duplicate'])->assertCreated();
        $this->report($many, ['reason' => 'fee_requested'])->assertCreated();
        $this->report($many, ['reason' => 'fee_requested'], $this->secondToken)->assertCreated();
        $this->holdByThree($held);

        $rows = $this->admin('GET', '/job-reports')->assertOk()->assertJsonPath('meta.total', 3)->json('data');

        $this->assertSame([$held, $many, $few], array_column($rows, 'job_id'));
        $this->assertNotContains($none, array_column($rows, 'job_id'));
        $this->assertSame(
            ['job_id' => $many, 'title' => 'Backend Developer', 'status' => 'active', 'company' => ['id' => $this->companyId, 'name' => 'PT-MAJU', 'slug' => 'pt-maju'], 'open_reports' => 2, 'reasons' => ['fee_requested' => 2],
                'first_reported_at' => '2026-10-05T09:00:00+00:00', 'last_reported_at' => '2026-10-05T09:00:00+00:00', 'held' => false, 'held_at' => null],
            $rows[1],
        );
        $this->assertTrue($rows[0]['held']);
        $this->assertSame('2026-10-05T09:00:00+00:00', $rows[0]['held_at']);
    }

    public function test_the_admin_reads_every_report_of_a_job(): void
    {
        $job = $this->liveJob();
        $this->report($job, ['reason' => 'discriminatory', 'note' => 'Men only.'])->assertCreated();
        $this->report($job, ['reason' => 'other', 'note' => 'Odd.'], $this->secondToken)->assertCreated();

        $rows = $this->admin('GET', "/jobs/{$job}/reports")->assertOk()->assertJsonPath('meta.total', 2)->json('data');

        $this->assertSame(['other', 'discriminatory'], array_column($rows, 'reason'), 'the newest first');
        $this->assertSame(
            ['id' => $rows[1]['id'], 'reason' => 'discriminatory', 'note' => 'Men only.', 'status' => 'open', 'reporter_user_id' => $this->seekerId,
                'created_at' => '2026-10-05T09:00:00+00:00', 'decided_at' => null, 'decided_by_admin_id' => null],
            $rows[1],
        );

        $this->admin('GET', '/jobs/999999/reports')->assertNotFound();
    }

    public function test_dismissing_closes_the_open_reports_lifts_the_hold_and_tells_the_reporters_without_the_outcome(): void
    {
        $job = $this->liveJob();
        $this->holdByThree($job);
        $adminId = (int) AdminModel::query()->where('email', 'ops@example.test')->value('id');

        $this->admin('POST', "/jobs/{$job}/reports/dismiss")->assertNoContent();

        $this->assertSame(3, DB::table('job_reports')->where('status', 'dismissed')->where('decided_by_admin_id', $adminId)->count());
        $this->assertSame(0, DB::table('job_reports')->where('status', 'open')->count());
        $this->assertNull($this->jobRow($job)->report_hold_at);
        $this->assertContains($job, $this->searchIds());
        $this->publicJob($job)->assertOk();

        foreach ([$this->seekerId, $this->secondId, $this->thirdId] as $reporter) {
            $this->assertSame([['type' => 'report.reviewed', 'data' => ['job_id' => $job]]], $this->notificationsOf($reporter));
        }

        $dismissed = DB::table('platform_audit_logs')->where('action', 'job.reports_dismissed')->first();
        $this->assertSame([$adminId, 'admin', 'job', $job], [(int) $dismissed->actor_id, $dismissed->actor_type, $dismissed->subject_type, (int) $dismissed->subject_id]);
        $this->assertSame(['closed' => 3], json_decode((string) $dismissed->metadata, true));
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'job.hold_released')->count());
    }

    public function test_dismissing_without_open_reports_is_refused_and_a_dismissed_reporter_may_report_again(): void
    {
        $job = $this->liveJob();

        $this->admin('POST', "/jobs/{$job}/reports/dismiss")->assertConflict()->assertJsonPath('type', 'urn:jobportal:error:no-open-reports');
        $this->admin('POST', '/jobs/999999/reports/dismiss')->assertNotFound();

        $this->report($job)->assertCreated();
        $this->admin('POST', "/jobs/{$job}/reports/dismiss")->assertNoContent();
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'job.hold_released')->count(), 'there was no hold to lift');

        $this->report($job)->assertCreated();
        $this->assertSame(['dismissed', 'open'], DB::table('job_reports')->orderBy('id')->pluck('status')->all());
    }

    public function test_taking_the_job_down_closes_the_reports_as_actioned_and_lifts_the_hold(): void
    {
        $job = $this->liveJob();
        $this->holdByThree($job);

        $this->admin('POST', "/jobs/{$job}/take-down", ['reason' => 'It asks for a fee.'])->assertOk()->assertJsonPath('data.status', 'taken_down');

        $this->assertSame(3, DB::table('job_reports')->where('status', 'actioned')->count());
        $this->assertNull($this->jobRow($job)->report_hold_at);
        $this->assertSame('taken_down', $this->jobRow($job)->status);

        foreach ([$this->seekerId, $this->secondId, $this->thirdId] as $reporter) {
            $this->assertSame([['type' => 'report.reviewed', 'data' => ['job_id' => $job]]], $this->notificationsOf($reporter));
        }

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'job.reports_dismissed')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'job.hold_released')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'job.taken_down')->count());
    }

    public function test_taking_down_a_job_without_reports_changes_nothing_else(): void
    {
        $job = $this->liveJob();

        $this->admin('POST', "/jobs/{$job}/take-down", ['reason' => 'Not allowed here.'])->assertOk();

        $this->assertSame(0, DB::table('job_reports')->count());
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'job.hold_released')->count());
    }

    public function test_the_admin_queue_card_counts_reported_and_held_jobs(): void
    {
        $this->report($this->liveJob())->assertCreated();
        $this->holdByThree($this->liveJob());

        $card = $this->api('GET', '/admin/dashboard', token: $this->opsToken)->assertOk()->json('data.cards.jobs_queue');

        $this->assertSame(['waiting_for_approval' => 0, 'job_reports' => ['jobs_with_open_reports' => 2, 'held' => 1]], $card);
    }

    // ----- erasure -----

    public function test_erasing_an_account_clears_the_reporter_and_the_note_but_keeps_the_report(): void
    {
        $job = $this->liveJob();
        $this->report($job, ['reason' => 'other', 'note' => 'My phone is 0812.'])->assertCreated();
        $this->report($job, ['reason' => 'spam_or_duplicate'], $this->secondToken)->assertCreated();

        $this->app->make(EraseJobReports::class)->execute($this->seekerId);

        $rows = DB::table('job_reports')->orderBy('id')->get();
        $this->assertSame([null, null, 'other'], [$rows[0]->reporter_user_id, $rows[0]->note, $rows[0]->reason]);
        $this->assertSame($this->secondId, (int) $rows[1]->reporter_user_id);

        $this->app->make(EraseJobReports::class)->execute($this->seekerId);
        $this->assertSame(2, DB::table('job_reports')->count());

        $listed = $this->admin('GET', "/jobs/{$job}/reports")->assertOk()->json('data');
        $this->assertSame([null, $this->secondId], array_column(array_reverse($listed), 'reporter_user_id'));
    }
}
