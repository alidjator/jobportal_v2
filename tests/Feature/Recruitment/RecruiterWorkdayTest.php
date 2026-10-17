<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-recruiter-workday.md` sections 2 and 4: time in stage and the stale mark, and the other applications of the same person. */
final class RecruiterWorkdayTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $adminJob;

    private int $sitiId;

    private int $budiId;

    private int $sitiOther;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->verifyTheCompany();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->adminJob = $this->insertJob('Data Analyst', 'active', [$this->adminId], attributes: ['deadline' => '2026-12-31']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->sitiId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->sitiOther = (int) $this->apply($this->adminJob)->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $budi = $this->seeker('budi.s@example.test', name: 'Budi Santoso');
        $budiToken = $this->tokenFor($budi);
        $this->budiId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $this->completeProfile($budiToken)], $budiToken)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function list(array|string $query = '', ?string $token = null): TestResponse
    {
        $query = is_array($query) ? '?'.http_build_query($query) : $query;

        return $this->api('GET', "/company/jobs/{$this->job}/applications{$query}", token: $token ?? $this->recruiterToken);
    }

    private function show(int $id, ?string $token = null): TestResponse
    {
        return $this->api('GET', "/company/applications/{$id}", token: $token ?? $this->adminToken);
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** The clock moves and the tokens are issued again, since they expire after a day. */
    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
    }

    private function enteredAt(int $id, string $utc): void
    {
        DB::table('recruitment_applications')->where('id', $id)->update(['stage_entered_at' => $utc]);
    }

    // ----- time in stage -----

    public function test_an_application_starts_the_clock_when_it_is_submitted_and_a_move_restarts_it(): void
    {
        $this->assertSame('2026-10-05 09:00:00', DB::table('recruitment_applications')->where('id', $this->sitiId)->value('stage_entered_at'));

        $this->moveClockTo('2026-10-14 08:00:00');
        $row = $this->list()->assertOk()->json('data');
        $siti = collect($row)->firstWhere('id', $this->sitiId);
        $this->assertSame(9, $siti['days_in_stage']);
        $this->assertSame('2026-10-05T09:00:00+00:00', $siti['stage_entered_at']);

        $this->api('POST', "/company/applications/{$this->sitiId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertNoContent();

        $moved = collect($this->list()->json('data'))->firstWhere('id', $this->sitiId);
        $this->assertSame(0, $moved['days_in_stage']);
        $this->assertFalse($moved['stale']);
        $this->assertSame('2026-10-14 08:00:00', DB::table('recruitment_applications')->where('id', $this->sitiId)->value('stage_entered_at'));
        $this->show($this->sitiId)->assertOk()->assertJsonPath('data.days_in_stage', 0)->assertJsonPath('data.stale', false)->assertJsonPath('data.stage_entered_at', '2026-10-14T08:00:00+00:00');
    }

    public function test_an_application_waiting_longer_than_the_limit_in_applied_or_screening_is_stale(): void
    {
        $this->moveClockTo('2026-10-14 08:00:00');

        $this->list()->assertOk()->assertJsonPath('data.0.stale', true)->assertJsonPath('data.1.stale', true);
        $this->list(['filter' => ['stale' => 'true']])->assertOk()->assertJsonCount(2, 'data');

        $this->api('POST', "/company/applications/{$this->sitiId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertNoContent();
        $this->list(['filter' => ['stale' => 'true']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->budiId);
        $this->list(['filter' => ['stale' => 'false']])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_only_the_first_two_stages_can_be_stale_and_the_limit_is_the_companys(): void
    {
        DB::table('recruitment_applications')->where('id', $this->sitiId)->update(['stage_id' => $this->stage('assessment'), 'stage_type' => 'assessment', 'stage_entered_at' => '2026-08-01 00:00:00']);
        $this->moveClockTo('2026-10-14 08:00:00');

        $siti = collect($this->list()->json('data'))->firstWhere('id', $this->sitiId);
        $this->assertGreaterThan(60, $siti['days_in_stage']);
        $this->assertFalse($siti['stale']);

        $this->app->make(SettingsStore::class)->setCompanyOverride('recruitment.stale_after_days', $this->companyId, 10, null);
        $this->list(['filter' => ['stale' => 'true']])->assertOk()->assertJsonCount(0, 'data');
        $this->show($this->budiId)->assertOk()->assertJsonPath('data.days_in_stage', 9)->assertJsonPath('data.stale', false);
    }

    public function test_the_days_are_whole_jakarta_days(): void
    {
        // Entered 2026-10-06 00:30 in Jakarta; on the 13th at 23:00 Jakarta it is 7 days, at 00:30 on the 14th it is 8.
        $this->enteredAt($this->budiId, '2026-10-05 17:30:00');

        $this->moveClockTo('2026-10-13 16:00:00');
        $budi = collect($this->list()->json('data'))->firstWhere('id', $this->budiId);
        $this->assertSame(7, $budi['days_in_stage']);
        $this->assertFalse($budi['stale']);

        $this->moveClockTo('2026-10-13 17:30:00');
        $budi = collect($this->list()->json('data'))->firstWhere('id', $this->budiId);
        $this->assertSame(8, $budi['days_in_stage']);
        $this->assertTrue($budi['stale']);
        $this->list(['filter' => ['stale' => '1']])->assertJsonPath('data.0.id', $this->budiId);
    }

    public function test_the_list_sorts_by_the_days_in_the_stage(): void
    {
        $this->enteredAt($this->budiId, '2026-10-10 10:00:00');
        $this->moveClockTo('2026-10-14 08:00:00');

        $this->list(['sort' => '-days_in_stage'])->assertOk()->assertJsonPath('data.0.id', $this->sitiId)->assertJsonPath('data.1.id', $this->budiId);
        $this->list(['sort' => 'days_in_stage'])->assertOk()->assertJsonPath('data.0.id', $this->budiId)->assertJsonPath('data.1.id', $this->sitiId);
        $this->list(['sort' => 'bogus'])->assertStatus(422);
    }

    // ----- other applications -----

    public function test_an_application_shows_the_other_applications_of_the_person_on_jobs_the_member_sees(): void
    {
        $detail = $this->show($this->sitiId)->assertOk();
        $detail->assertJsonCount(1, 'data.other_applications')->assertJsonPath('data.other_applications.0.id', $this->sitiOther)->assertJsonPath('data.other_applications.0.job.title', 'Data Analyst')
            ->assertJsonPath('data.other_applications.0.stage.type', 'applied')->assertJsonPath('data.others_hidden', 0);

        $recruiter = $this->show($this->sitiId, $this->recruiterToken)->assertOk();
        $recruiter->assertJsonCount(0, 'data.other_applications')->assertJsonPath('data.others_hidden', 1);

        $this->show($this->budiId)->assertOk()->assertJsonCount(0, 'data.other_applications')->assertJsonPath('data.others_hidden', 0);
    }

    public function test_the_pipeline_counts_the_active_other_applications(): void
    {
        $siti = collect($this->list(token: $this->adminToken)->json('data'))->firstWhere('id', $this->sitiId);
        $this->assertSame(1, $siti['other_applications_count']);
        $this->assertSame(0, collect($this->list(token: $this->adminToken)->json('data'))->firstWhere('id', $this->budiId)['other_applications_count']);
        $this->assertSame(0, collect($this->list()->json('data'))->firstWhere('id', $this->sitiId)['other_applications_count']);

        $this->api('POST', "/company/applications/{$this->sitiOther}/reject", ['reason' => 'position_filled'], $this->adminToken)->assertNoContent();
        $this->assertSame(0, collect($this->list(token: $this->adminToken)->json('data'))->firstWhere('id', $this->sitiId)['other_applications_count']);
        $this->show($this->sitiId)->assertJsonCount(1, 'data.other_applications')->assertJsonPath('data.other_applications.0.stage.type', 'rejected');
    }

    public function test_an_anonymised_applicant_shows_no_other_applications(): void
    {
        DB::table('recruitment_applications')->where('id', $this->sitiId)->update(['profile_snapshot' => null]);

        $this->show($this->sitiId)->assertOk()->assertJsonCount(0, 'data.other_applications')->assertJsonPath('data.others_hidden', 0);
        $this->assertSame(0, collect($this->list(token: $this->adminToken)->json('data'))->firstWhere('id', $this->sitiId)['other_applications_count']);
    }

    public function test_at_most_ten_other_applications_are_listed(): void
    {
        $template = (array) DB::table('recruitment_applications')->where('id', $this->sitiOther)->first();
        unset($template['id']);

        for ($i = 0; $i < 11; $i++) {
            DB::table('recruitment_applications')->insert([...$template, 'active_key' => null, 'applied_at' => now()->addMinutes($i + 1)]);
        }

        $this->show($this->sitiId)->assertOk()->assertJsonCount(10, 'data.other_applications');
    }
}
