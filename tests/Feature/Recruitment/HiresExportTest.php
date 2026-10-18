<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Src\Platform\Application\Audit\AuditLogRepository;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-hiring.md` section 3: the hires of a period across the jobs the member may see, as a CSV. */
final class HiresExportTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $jobA;

    private int $jobB;

    private int $siti;

    private int $budi;

    private int $ani;

    private int $cici;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->buildApplicationFixtures();
        $this->verifyTheCompany();
        $this->jobA = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->jobB = $this->insertJob('Data Analyst', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);

        $this->siti = (int) $this->apply($this->jobA)->assertCreated()->json('data.id');
        $this->budi = $this->applyAs('budi.s@example.test', $this->jobA, 'Budi Santoso');
        $this->ani = $this->applyAs('ani.w@example.test', $this->jobA, 'Ani Wijaya');
        $this->cici = $this->applyAs('cici.h@example.test', $this->jobB, 'Cici Hartono');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function applyAs(string $email, int $job, string $name): int
    {
        $token = $this->tokenFor($this->seeker($email, name: $name));
        $this->resumeId = $this->completeProfile($token);

        return (int) $this->apply($job, token: $token)->assertCreated()->json('data.id');
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** Moves the clock and signs the members in again, because the access tokens expire. */
    private function moveClockTo(string $at): void
    {
        Carbon::setTestNow($at);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->secondRecruiterToken = $this->tokenFor($this->secondRecruiterId);
        $this->otherAdminToken = $this->tokenFor($this->otherAdminId);
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    /** Hires the application at the given moment (UTC), with a join date three weeks later. */
    private function hire(int $application, string $at, string $by = 'recruiter'): void
    {
        $this->moveClockTo($at);
        $token = $by === 'admin' ? $this->adminToken : $this->recruiterToken;
        $this->api('POST', "/company/applications/{$application}/move", ['stage_id' => $this->stage('hired'), 'hire' => ['join_date' => now('Asia/Jakarta')->addDays(21)->format('Y-m-d')]], $token)->assertOk();
    }

    private function hireAll(): void
    {
        $this->hire($this->siti, '2026-10-05 10:00:00');
        $this->hire($this->budi, '2026-10-05 18:00:00');
        $this->hire($this->ani, '2026-10-20 08:00:00');

        // The second job's hire comes from an accepted offer.
        $this->moveClockTo('2026-10-07 03:00:00');
        $this->api('POST', "/company/applications/{$this->cici}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails(['position' => 'Senior Analyst', 'salary' => 15_000_000, 'start_date' => '2026-11-02'])], $this->adminToken)->assertOk();
        $this->moveClockTo('2026-10-07 04:30:00');
        $this->api('POST', "/me/applications/{$this->cici}/offer/accept", token: $this->tokenFor((int) DB::table('recruitment_applications')->where('id', $this->cici)->value('user_id')))->assertOk();

        $this->moveClockTo('2026-10-25 09:00:00');
    }

    private function export(array $query = [], ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/hires/export?'.http_build_query([...['from' => '2026-10-01', 'to' => '2026-10-31'], ...$query]), token: $token ?? $this->adminToken);
    }

    /** @return list<list<string|null>> the lines of the file, the header first */
    private function lines(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $lines = [];

        while (($line = fgetcsv($stream, escape: '')) !== false) {
            $lines[] = $line;
        }

        return $lines;
    }

    /** @return list<string> the application ids of the file, in order */
    private function ids(TestResponse $response): array
    {
        return array_column(array_slice($this->lines($response), 1), 0);
    }

    private function audit(): array
    {
        return DB::table('platform_audit_logs')->where('action', 'export.hires')->orderBy('id')->get()->all();
    }

    public function test_the_export_lists_the_hires_of_the_period_oldest_first_with_the_accepted_offer(): void
    {
        $this->hireAll();

        $response = $this->export()->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('hires-2026-10-01-2026-10-31.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $lines = $this->lines($response);
        $this->assertSame([
            'Application ID', 'Hired at', 'Join date', 'Name', 'Email', 'Phone', 'City', 'Job ID', 'Job title',
            'Offer position', 'Offer salary (IDR)', 'Offer start date', 'Offer accepted at',
        ], $lines[0]);
        $this->assertCount(5, $lines);
        $this->assertSame([(string) $this->siti, (string) $this->budi, (string) $this->cici, (string) $this->ani], array_column(array_slice($lines, 1), 0));

        $this->assertSame([(string) $this->siti, '2026-10-05T10:00:00Z', '2026-10-26', 'Siti Rahma', 'siti@example.test'], array_slice($lines[1], 0, 5));
        $this->assertSame([(string) $this->jobA, 'Backend Developer', '', '', '', ''], array_slice($lines[1], 7));

        $this->assertSame([(string) $this->cici, '2026-10-07T04:30:00Z', '2026-11-02', 'Cici Hartono'], [$lines[3][0], $lines[3][1], $lines[3][2], $lines[3][3]]);
        $this->assertSame([(string) $this->jobB, 'Data Analyst', 'Senior Analyst', '15000000', '2026-11-02', '2026-10-07T04:30:00Z'], array_slice($lines[3], 7));
    }

    public function test_the_days_are_jakarta_days(): void
    {
        $this->hireAll();

        // 18:00 UTC on 5 October is 01:00 on 6 October in Jakarta.
        $this->assertSame([(string) $this->siti], $this->ids($this->export(['from' => '2026-10-05', 'to' => '2026-10-05'])));
        $this->assertSame([(string) $this->budi], $this->ids($this->export(['from' => '2026-10-06', 'to' => '2026-10-06'])));
        $this->assertSame([(string) $this->siti, (string) $this->budi], $this->ids($this->export(['from' => '2026-10-05', 'to' => '2026-10-06'])));
        $this->assertSame([], $this->ids($this->export(['from' => '2026-11-01', 'to' => '2026-11-30'])));
    }

    public function test_only_applications_now_in_hired_count_and_the_last_move_into_hired_is_the_hire(): void
    {
        $this->hireAll();

        // Corrected back to another stage: no longer a hire.
        $this->api('POST', "/company/applications/{$this->ani}/move", ['stage_id' => $this->stage('screening')], $this->adminToken)->assertOk();
        $this->assertSame([(string) $this->siti, (string) $this->budi, (string) $this->cici], $this->ids($this->export()));

        // Hired again later: the later move counts, not the first one.
        $this->api('POST', "/company/applications/{$this->siti}/move", ['stage_id' => $this->stage('screening')], $this->adminToken)->assertOk();
        $this->hire($this->siti, '2026-10-26 05:00:00', 'admin');

        $this->assertNotContains((string) $this->siti, $this->ids($this->export(['from' => '2026-10-01', 'to' => '2026-10-25'])));
        $this->assertContains((string) $this->siti, $this->ids($this->export(['from' => '2026-10-26', 'to' => '2026-10-26'])));
    }

    public function test_the_period_must_be_valid(): void
    {
        $this->export(['from' => '2026-10-31', 'to' => '2026-10-01'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-export-range');
        $this->export(['from' => '2026-01-01', 'to' => '2027-01-02'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-export-range');
        $this->export(['from' => '2026-01-01', 'to' => '2027-01-01'])->assertOk();
        $this->export(['from' => '2026-10-05', 'to' => '2026-10-05'])->assertOk();

        $this->export(['from' => '05-10-2026'])->assertStatus(422);
        $this->api('GET', '/company/hires/export?from=2026-10-01', token: $this->adminToken)->assertStatus(422);
        $this->api('GET', '/company/hires/export', token: $this->adminToken)->assertStatus(422);
        $this->export(['filter' => ['company_id' => 1]])->assertStatus(422);

        $this->assertCount(2, $this->audit());
    }

    public function test_the_jobs_a_member_may_see_limit_the_export(): void
    {
        $this->hireAll();

        $this->assertSame([(string) $this->siti, (string) $this->budi, (string) $this->ani], $this->ids($this->export(token: $this->recruiterToken)));
        $this->assertSame([(string) $this->cici], $this->ids($this->export(token: $this->secondRecruiterToken)));

        $this->assertSame([(string) $this->cici], $this->ids($this->export(['filter' => ['job_id' => $this->jobB]])));
        $this->assertSame([(string) $this->siti, (string) $this->budi, (string) $this->ani], $this->ids($this->export(['filter' => ['job_id' => $this->jobA]])));

        $this->export(['filter' => ['job_id' => $this->jobB]], $this->recruiterToken)->assertStatus(404);
        $this->export(['filter' => ['job_id' => 999999]])->assertStatus(404);

        $this->assertSame([], $this->ids($this->export(token: $this->otherAdminToken)));
        $this->export(['filter' => ['job_id' => $this->jobA]], $this->otherAdminToken)->assertStatus(404);
    }

    public function test_a_hiring_manager_sees_only_the_hires_that_carry_the_marker_on_their_bound_job(): void
    {
        $this->hireAll();
        DB::table('job_assignments')->where('job_id', $this->jobA)->where('user_id', $this->recruiterId)->update(['role' => 'hiring_manager']);
        // Moving into `hired` already sets the marker (it is past the boundary), so the test clears it from the others.
        DB::table('recruitment_applications')->whereIn('id', [$this->siti, $this->ani])->update(['hiring_manager_visible_at' => null]);
        DB::table('recruitment_applications')->where('id', $this->budi)->update(['hiring_manager_visible_at' => now()]);

        $this->assertSame([(string) $this->budi], $this->ids($this->export(token: $this->recruiterToken)));
    }

    public function test_an_anonymised_hire_keeps_a_row_without_personal_data(): void
    {
        $this->hireAll();
        DB::table('recruitment_applications')->where('id', $this->siti)->update(['profile_snapshot' => null]);

        $row = $this->lines($this->export())[1];

        $this->assertSame([(string) $this->siti, '2026-10-05T10:00:00Z', '2026-10-26', 'Deleted account', '', '', ''], array_slice($row, 0, 7));
        $this->assertSame('Backend Developer', $row[8]);
    }

    public function test_an_applicants_text_cannot_run_as_a_spreadsheet_formula(): void
    {
        $this->hireAll();
        $snapshot = json_decode((string) DB::table('recruitment_applications')->where('id', $this->siti)->value('profile_snapshot'), true);
        $snapshot['name'] = '=HYPERLINK("http://evil.test","click")';
        DB::table('recruitment_applications')->where('id', $this->siti)->update(['profile_snapshot' => json_encode($snapshot)]);

        $this->assertSame('\'=HYPERLINK("http://evil.test","click")', $this->lines($this->export())[1][3]);
    }

    public function test_every_export_is_audited_without_personal_data(): void
    {
        $this->hireAll();

        $this->export(['filter' => ['job_id' => $this->jobA], 'from' => '2026-10-05', 'to' => '2026-10-06'])->assertOk();

        $entries = $this->audit();
        $this->assertCount(1, $entries);
        $this->assertSame($this->adminId, (int) $entries[0]->actor_id);
        $this->assertSame('company', $entries[0]->subject_type);
        $this->assertSame($this->companyId, (int) $entries[0]->subject_id);
        $this->assertEquals(['from' => '2026-10-05', 'to' => '2026-10-06', 'job_id' => $this->jobA, 'row_count' => 2, 'company_id' => $this->companyId], json_decode($entries[0]->metadata, true));

        $this->export()->assertOk();
        $this->assertCount(2, $this->audit());
        $this->assertNull(json_decode($this->audit()[1]->metadata, true)['job_id']);
    }

    public function test_an_export_that_cannot_be_audited_is_not_sent(): void
    {
        $this->hireAll();
        $this->mock(AuditLogRepository::class, fn ($mock) => $mock->shouldReceive('append')->andThrow(new RuntimeException('audit store is down')));

        $response = $this->export();

        $response->assertStatus(500);
        $this->assertStringNotContainsString('siti@example.test', $response->getContent());
    }

    public function test_the_export_needs_the_export_permission(): void
    {
        $this->hireAll();

        $viewer = $this->memberWith(['applications.view']);
        $this->export(token: $viewer['token'])->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');

        $exporter = $this->memberWith(['applications.view', 'applications.export'], 'exporter@example.test');
        $this->export(token: $exporter['token'])->assertOk();

        $this->export(token: $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->assertCount(1, $this->audit());
    }

    public function test_the_export_needs_a_signed_in_account(): void
    {
        // The set-up calls left a bearer token on the client.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->getJson('/api/v1/company/hires/export?from=2026-10-01&to=2026-10-31')->assertStatus(401);
        $this->assertCount(0, $this->audit());
    }

    public function test_a_member_can_export_ten_times_an_hour(): void
    {
        foreach (range(1, 10) as $_) {
            $this->export()->assertOk();
        }

        $this->export()->assertStatus(429);
        $this->export(token: $this->recruiterToken)->assertOk();
    }

    public function test_a_selection_over_the_row_cap_is_refused_before_anything_is_sent_or_audited(): void
    {
        $this->hireAll();
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.max_export_rows', 1, null);

        $this->export()->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:export-too-large')->assertJsonPath('max_rows', 1);
        $this->assertCount(0, $this->audit());

        $this->assertCount(2, $this->lines($this->export(['from' => '2026-10-20', 'to' => '2026-10-20'])->assertOk()));
        $this->assertCount(1, $this->audit());
    }
}
