<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-rejections.md` section 3: a job may let a rejected applicant apply again after a waiting period (`reapply_after_days`). Without one, a rejected,
 * hired or pending application blocks the job as before.
 */
final class ReapplyAfterRejectionTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

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

    /** A live job the applicant applied to on 5 October, 16:00 in Jakarta. */
    private function appliedTo(?int $days): void
    {
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31', 'reapply_after_days' => $days]);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    private function reject(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();
    }

    /** Applies again at the given time (UTC), with a token that has not expired by then. */
    private function applyAt(string $time): TestResponse
    {
        Carbon::setTestNow($time);

        return $this->apply($this->job, token: $this->tokenFor($this->seekerId));
    }

    // ----- the waiting period -----

    public function test_a_job_without_a_waiting_period_keeps_a_rejected_applicant_out_for_good(): void
    {
        $this->appliedTo(null);
        $this->reject();

        $this->applyAt('2026-12-20 09:00:00')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-applied')->assertJsonMissingPath('reapply_on');
    }

    public function test_during_the_waiting_period_the_answer_says_from_which_date_the_applicant_may_apply(): void
    {
        $this->appliedTo(30);
        $this->reject();

        $this->apply($this->job)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-applied')->assertJsonPath('reapply_on', '2026-11-04');
        $this->applyAt('2026-11-03 16:59:59')->assertStatus(409)->assertJsonPath('reapply_on', '2026-11-04');
        $this->assertSame(1, DB::table('recruitment_applications')->count());
    }

    public function test_the_first_day_after_the_period_the_applicant_can_apply_again_and_the_old_application_stays_as_it_was(): void
    {
        $this->appliedTo(30);
        $this->reject();
        $old = DB::table('recruitment_applications')->find($this->applicationId);

        // 00:00 on 4 November in Jakarta.
        $response = $this->applyAt('2026-11-03 17:00:00')->assertCreated();

        $new = (int) $response->json('data.id');
        $this->assertNotSame($this->applicationId, $new);
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $new)->value('stage_type'));
        $this->assertSame("{$this->seekerId}-{$this->job}", DB::table('recruitment_applications')->where('id', $new)->value('active_key'));

        $kept = DB::table('recruitment_applications')->find($this->applicationId);
        $this->assertSame(['rejected', null, $old->stage_id], [$kept->stage_type, $kept->active_key, $kept->stage_id]);
        $this->assertSame(2, DB::table('recruitment_applications')->where('job_id', $this->job)->count());
        $this->assertSame(1, DB::table('recruitment_application_stage_history')->where('application_id', $new)->count());
    }

    public function test_the_new_application_blocks_the_job_like_any_other(): void
    {
        $this->appliedTo(30);
        $this->reject();
        $this->applyAt('2026-11-10 09:00:00')->assertCreated();

        $this->apply($this->job, token: $this->tokenFor($this->seekerId))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-applied')->assertJsonMissingPath('reapply_on');
    }

    public function test_a_second_rejection_starts_a_new_waiting_period(): void
    {
        $this->appliedTo(30);
        $this->reject();
        $second = (int) $this->applyAt('2026-11-10 09:00:00')->assertCreated()->json('data.id');
        $this->api('POST', "/company/applications/{$second}/reject", ['reason' => 'position_filled'], $this->tokenFor($this->recruiterId))->assertNoContent();

        $this->applyAt('2026-11-20 09:00:00')->assertStatus(409)->assertJsonPath('reapply_on', '2026-12-10');
        $this->applyAt('2026-12-10 09:00:00')->assertCreated();
        $this->assertSame(3, DB::table('recruitment_applications')->where('job_id', $this->job)->count());
    }

    public function test_the_daily_limit_counts_the_new_application(): void
    {
        $this->appliedTo(30);
        $this->reject();
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.max_applications_per_day', 1, null);

        $this->applyAt('2026-11-10 09:00:00')->assertCreated();
        $other = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        $this->apply($other, token: $this->tokenFor($this->seekerId))->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:application-limit-reached');
    }

    // ----- what never frees the job -----

    public function test_a_pending_application_never_frees_the_job(): void
    {
        $this->appliedTo(30);

        $this->applyAt('2026-12-20 09:00:00')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-applied')->assertJsonMissingPath('reapply_on');
    }

    public function test_a_hired_application_blocks_the_job_for_good(): void
    {
        $this->appliedTo(30);
        $hired = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'hired')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $hired, 'hire' => ['join_date' => '2026-11-01']], $this->recruiterToken)->assertOk();

        $this->applyAt('2026-12-20 09:00:00')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-applied')->assertJsonMissingPath('reapply_on');
    }

    public function test_a_withdrawn_application_still_lets_the_applicant_apply_again_at_once(): void
    {
        $this->appliedTo(null);
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", [], $this->seekerToken)->assertNoContent();

        $this->apply($this->job)->assertCreated();
    }

    // ----- the company default -----

    public function test_the_company_default_of_the_waiting_period_is_a_setting_the_company_admin_changes(): void
    {
        $setting = collect($this->api('GET', '/company/settings', [], $this->adminToken)->assertOk()->json('data'))->firstWhere('key', 'recruitment.reapply_after_days');
        $this->assertSame(['integer', 0, 0, 365], [$setting['type'], $setting['default'], $setting['min'], $setting['max']]);

        $saved = collect($this->api('PATCH', '/company/settings', ['recruitment.reapply_after_days' => 90], $this->adminToken)->assertOk()->json('data'))->firstWhere('key', 'recruitment.reapply_after_days');
        $this->assertSame(90, $saved['value']);

        $this->api('PATCH', '/company/settings', ['recruitment.reapply_after_days' => 400], $this->adminToken)->assertStatus(422);
        $this->api('PATCH', '/company/settings', ['recruitment.reapply_after_days' => 60], $this->recruiterToken)->assertForbidden();
    }
}
