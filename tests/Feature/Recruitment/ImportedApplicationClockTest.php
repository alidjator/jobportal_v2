<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-activation.md` section 6: an imported application has no time in stage and is never stale until its stage is first changed. */
final class ImportedApplicationClockTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $importedId;

    private int $nativeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->verifyTheCompany();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->importedId = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $budi = $this->seeker('budi.s@example.test', name: 'Budi Santoso');
        $budiToken = $this->tokenFor($budi);
        $this->nativeId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $this->completeProfile($budiToken)], $budiToken)->assertCreated()->json('data.id');

        // The first one was imported at noon, after the time it entered its stage.
        DB::table('recruitment_applications')->where('id', $this->importedId)->update(['migrated_at' => '2026-10-05 12:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function list(array|string $query = ''): TestResponse
    {
        $query = is_array($query) ? '?'.http_build_query($query) : $query;

        return $this->api('GET', "/company/jobs/{$this->job}/applications{$query}", token: $this->recruiterToken);
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function staleTasks(): int
    {
        return (int) collect($this->api('GET', '/company/tasks', token: $this->recruiterToken)->assertOk()->json('data.items'))->firstWhere('key', 'stale_applications')['count'];
    }

    public function test_an_untouched_import_has_no_time_in_stage_and_is_not_stale_while_a_native_application_is(): void
    {
        $this->moveClockTo('2026-10-14 08:00:00');

        $rows = collect($this->list()->assertOk()->json('data'));
        $imported = $rows->firstWhere('id', $this->importedId);
        $native = $rows->firstWhere('id', $this->nativeId);

        $this->assertNull($imported['days_in_stage']);
        $this->assertFalse($imported['stale']);
        $this->assertTrue($imported['migrated']);
        $this->assertSame('2026-10-05T12:00:00+00:00', $imported['migrated_at']);
        $this->assertSame(9, $native['days_in_stage']);
        $this->assertTrue($native['stale']);
        $this->assertFalse($native['migrated']);
        $this->assertNull($native['migrated_at']);
    }

    public function test_the_stale_filter_and_the_task_count_leave_the_untouched_import_out(): void
    {
        $this->moveClockTo('2026-10-14 08:00:00');

        $this->list(['filter' => ['stale' => 'true']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->nativeId);
        $this->list(['filter' => ['stale' => 'false']])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(1, $this->staleTasks());
    }

    public function test_the_detail_shows_the_label_and_no_time_in_stage(): void
    {
        $this->moveClockTo('2026-10-14 08:00:00');

        $this->api('GET', "/company/applications/{$this->importedId}", token: $this->adminToken)->assertOk()
            ->assertJsonPath('data.days_in_stage', null)->assertJsonPath('data.stale', false)->assertJsonPath('data.migrated', true);
        $this->api('GET', "/company/applications/{$this->nativeId}", token: $this->adminToken)->assertOk()
            ->assertJsonPath('data.days_in_stage', 9)->assertJsonPath('data.stale', true)->assertJsonPath('data.migrated', false);
    }

    public function test_the_first_move_starts_the_clock_and_the_application_can_then_become_stale(): void
    {
        $this->moveClockTo('2026-10-14 08:00:00');
        $this->api('POST', "/company/applications/{$this->importedId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertOk();

        $moved = collect($this->list()->json('data'))->firstWhere('id', $this->importedId);
        $this->assertSame(0, $moved['days_in_stage']);
        $this->assertFalse($moved['stale']);
        $this->assertTrue($moved['migrated'], 'The label stays.');

        $this->moveClockTo('2026-10-30 08:00:00');

        $later = collect($this->list()->json('data'))->firstWhere('id', $this->importedId);
        $this->assertSame(16, $later['days_in_stage']);
        $this->assertTrue($later['stale']);
        $this->assertSame(2, $this->staleTasks());
    }

    public function test_the_job_seeker_sees_the_label_in_the_list_and_the_detail(): void
    {
        $list = $this->api('GET', '/me/applications', token: $this->seekerToken)->assertOk();
        $this->assertTrue($list->json('data.0.migrated'));
        $this->assertSame('2026-10-05T12:00:00+00:00', $list->json('data.0.migrated_at'));

        $this->api('GET', "/me/applications/{$this->importedId}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.migrated', true);
    }
}
