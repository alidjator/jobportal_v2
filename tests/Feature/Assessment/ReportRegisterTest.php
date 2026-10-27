<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Assessment\Application\EraseAssessmentData;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * The report register of a provider (`docs/design/phase-8-provider-tools.md` section 8): the signed and automatically issued reports of its psychologists, newest first, with no candidate in it, read by a psychologist of the provider
 * and by a platform admin, and downloaded as a file that is audited. The clock is frozen at 09:00 on 2026-10-05 (16:00 in Jakarta).
 */
final class ReportRegisterTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $applicationId;

    private int $invitationId = 0;

    private ?string $attemptSession = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = []): TestResponse
    {
        $response = $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $this->seekerToken, $this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession]);

        if ($uri === 'start' && is_string($session = $response->json('data.attempt_session'))) {
            $this->attemptSession = $session;
        }

        return $response;
    }

    private function psychologistId(?int $providerId = null): int
    {
        return (int) DB::table('assessment_psychologists')->where('provider_id', $providerId ?? $this->providerId)->orderBy('id')->value('id');
    }

    /** A battery of one Likert section with norms that explain themselves, the two templates and a recommendation rule; published, granted to the company. */
    private function battery(): int
    {
        $id = $this->newTest('provider', ['title' => 'Personality']);

        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [
            ['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 2, 'standard_score' => 40, 'band' => 'Low', 'explanation' => 'You prefer quiet settings.'],
            ['scale_key' => 'extraversion', 'raw_min' => 3, 'raw_max' => 10, 'standard_score' => 60, 'band' => 'High', 'explanation' => 'You like people and often seek company.'],
        ]])->assertOk();
        $this->provider('PUT', "tests/{$id}/recommendation-rules", ['key_scales' => ['extraversion'], 'recommended_min' => 55, 'not_yet_below' => 45])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Dear {{candidate_name}}: {{test_title}} - extraversion {{scale:extraversion:raw}}. No. {{report_number}} by {{psychologist_name}} on {{signed_on}}.'])->assertOk();
        $this->provider('PUT', "tests/{$id}/explanation-template", ['body' => 'Dear {{candidate_name}}: {{scale:extraversion:explanation}} ({{report_number}}).'])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        return $id;
    }

    /** The provider allows automatic issue and the battery is set up to issue at once. */
    private function readyBattery(): int
    {
        $this->assessmentAdmin('PUT', "providers/{$this->providerId}/auto-issue", ['allowed' => true])->assertOk();
        $battery = $this->battery();
        $this->provider('PUT', "tests/{$battery}/auto-issue", ['enabled' => true, 'responsible_psychologist_id' => $this->psychologistId()])->assertOk();

        return $battery;
    }

    /** The candidate takes the battery ("Always": raw score 3) and hands it in. Returns the report id. */
    private function takeBattery(int $battery): int
    {
        $due = Carbon::now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $battery, 'due_at' => $due]], $this->recruiterToken)->assertOk();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');

        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        $likert = (int) $this->take('GET', 'attempt')->json('data.questions.0.id');
        $this->take('PUT', "attempt/questions/{$likert}/answer", ['option_positions' => [3]])->assertNoContent();
        $this->take('POST', 'attempt/submit')->assertOk();

        return (int) DB::table('assessment_reports')->value('id');
    }

    /** @param  array<string, string>  $filter */
    private function register(array $filter = [], ?string $token = null): TestResponse
    {
        $query = $filter === [] ? '' : '?'.http_build_query(['filter' => $filter]);

        return $this->provider('GET', "register{$query}", [], $token);
    }

    /** @return list<list<string|null>> */
    private function lines(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'The file starts with a byte order mark so that Excel reads it right.');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $lines = [];

        while (($line = fgetcsv($stream, escape: '')) !== false) {
            $lines[] = $line;
        }

        return $lines;
    }

    // ----- the register -----

    public function test_it_lists_an_automatic_report_with_who_answers_for_it_and_no_candidate(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);

        $item = $this->register()->assertOk()->assertJsonPath('meta.total', 1)->json('data.0');

        $keys = array_keys($item);
        sort($keys);
        $this->assertSame(['auto_issued', 'company_name', 'erased', 'id', 'psychologist_id', 'recommendation', 'report_number', 'reviewed', 'revision', 'signed_at', 'signer_name', 'test_id', 'test_title'], $keys, 'No candidate, no job and no scale.');
        $this->assertSame($reportId, $item['id']);
        $this->assertSame('LEMB/2026/00001', $item['report_number']);
        $this->assertSame(0, $item['revision']);
        $this->assertSame($battery, $item['test_id']);
        $this->assertSame('Personality', $item['test_title']);
        $this->assertSame($this->psychologistId(), $item['psychologist_id']);
        $this->assertTrue($item['auto_issued']);
        $this->assertFalse($item['reviewed'], 'An automatic report waits for a psychologist to look at it.');
        $this->assertSame('recommended', $item['recommendation']);
        $this->assertFalse($item['erased']);
        $this->assertStringStartsWith('2026-10-05T09:00:00', $item['signed_at']);
        $this->assertNotNull($item['company_name']);

        $this->provider('POST', "reviews/{$reportId}/mark-reviewed")->assertOk();
        $this->register()->assertOk()->assertJsonPath('data.0.reviewed', true);
    }

    public function test_a_report_that_still_waits_for_a_signature_is_not_in_the_register(): void
    {
        $battery = $this->battery();
        $this->takeBattery($battery);

        $this->assertSame('pending_review', DB::table('assessment_reports')->value('status'));
        $this->register()->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_the_filters_narrow_by_day_psychologist_and_battery(): void
    {
        $battery = $this->readyBattery();
        $this->takeBattery($battery);

        $this->register(['from' => '2026-10-05', 'to' => '2026-10-05'])->assertOk()->assertJsonPath('meta.total', 1);
        $this->register(['to' => '2026-10-04'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->register(['from' => '2026-10-06'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->register(['psychologist_id' => (string) $this->psychologistId()])->assertOk()->assertJsonPath('meta.total', 1);
        $this->register(['psychologist_id' => (string) $this->psychologistId($this->otherProviderId)])->assertOk()->assertJsonPath('meta.total', 0);
        $this->register(['test_id' => (string) $battery])->assertOk()->assertJsonPath('meta.total', 1);
        $this->register(['test_id' => (string) ($battery + 1000)])->assertOk()->assertJsonPath('meta.total', 0);

        $this->register(['from' => '2026-10-06', 'to' => '2026-10-05'])->assertStatus(422);
        $this->register(['from' => '5 October'])->assertStatus(422);
        $this->provider('GET', 'register?filter[candidate]=budi')->assertStatus(422);
    }

    public function test_another_provider_reads_only_its_own_register(): void
    {
        $this->takeBattery($this->readyBattery());

        $this->register([], $this->otherPsychologistToken)->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_only_a_psychologist_reads_the_provider_register(): void
    {
        $this->api('GET', '/provider/register', [], $this->recruiterToken)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/provider/register')->assertUnauthorized();
        $this->api('GET', '/provider/register/export?filter[from]=2026-10-01&filter[to]=2026-10-05', [], $this->recruiterToken)->assertForbidden();
    }

    public function test_an_erased_candidate_keeps_the_line_and_it_says_so(): void
    {
        $this->takeBattery($this->readyBattery());

        $this->app->make(EraseAssessmentData::class)->execute($this->seekerId);

        $this->register()->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.erased', true)->assertJsonPath('data.0.report_number', 'LEMB/2026/00001');
    }

    // ----- the file -----

    public function test_the_export_is_a_csv_of_the_register_and_it_is_audited(): void
    {
        $this->takeBattery($this->readyBattery());

        $response = $this->provider('GET', 'register/export?filter[from]=2026-10-01&filter[to]=2026-10-31')->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('report-register-2026-10-01-2026-10-31.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $lines = $this->lines($response);
        $this->assertCount(2, $lines);
        $this->assertSame(['Report number', 'Revision', 'Signed at', 'Signed by', 'Battery', 'Company', 'Issued automatically', 'Reviewed', 'Recommendation', 'Candidate data erased'], $lines[0]);
        $this->assertSame('LEMB/2026/00001', $lines[1][0]);
        $this->assertSame('', $lines[1][1]);
        $this->assertSame('Personality', $lines[1][4]);
        $this->assertSame(['yes', 'no', 'recommended', 'no'], array_slice($lines[1], 6));

        $entries = DB::table('platform_audit_logs')->where('action', 'assessment.register_exported')->get()->all();
        $this->assertCount(1, $entries);
        $this->assertSame($this->providerId, (int) $entries[0]->subject_id);
        $this->assertEquals(
            ['filters' => ['from' => '2026-10-01', 'to' => '2026-10-31', 'psychologist_id' => null, 'test_id' => null], 'row_count' => 1],
            json_decode($entries[0]->metadata, true),
        );
    }

    public function test_the_export_needs_a_range_of_at_most_366_days(): void
    {
        $this->provider('GET', 'register/export')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:register-range-required');
        $this->provider('GET', 'register/export?filter[from]=2026-10-01')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:register-range-required');
        $this->provider('GET', 'register/export?filter[from]=2025-01-01&filter[to]=2026-10-05')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:register-range-too-long');
        $this->provider('GET', 'register/export?filter[from]=2025-10-05&filter[to]=2026-10-05')->assertOk();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'assessment.register_exported')->count(), 'Only the file that was sent is audited.');
    }

    public function test_the_export_is_limited_to_ten_files_an_hour(): void
    {
        for ($file = 1; $file <= 10; $file++) {
            $this->provider('GET', 'register/export?filter[from]=2026-10-01&filter[to]=2026-10-05')->assertOk();
        }

        $this->provider('GET', 'register/export?filter[from]=2026-10-01&filter[to]=2026-10-05')->assertStatus(429);
    }

    // ----- the platform admin -----

    public function test_a_platform_admin_reads_the_register_of_a_provider(): void
    {
        $this->takeBattery($this->readyBattery());

        $this->assessmentAdmin('GET', "providers/{$this->providerId}/register")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.report_number', 'LEMB/2026/00001');
        $this->assessmentAdmin('GET', "providers/{$this->providerId}/register?filter[test_id]=999999")->assertOk()->assertJsonPath('meta.total', 0);
        $this->assessmentAdmin('GET', "providers/{$this->otherProviderId}/register")->assertOk()->assertJsonPath('meta.total', 0);
        $this->assessmentAdmin('GET', 'providers/999999/register')->assertNotFound();

        $limited = AdminModel::query()->create(['name' => 'Library only', 'email' => 'library@example.test', 'password' => 'hash', 'status' => 'active']);
        $limited->givePermissionTo(['assessment_library.manage']);
        $this->assessmentAdmin('GET', "providers/{$this->providerId}/register", [], $limited->createToken('console', ['*'], now()->addHour())->plainTextToken)->assertForbidden();
    }
}
