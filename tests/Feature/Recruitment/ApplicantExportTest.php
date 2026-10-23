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

final class ApplicantExportTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $firstId;

    private int $secondId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->buildApplicationFixtures();
        $this->verifyTheCompany();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        $relocate = $this->question('yes_no', 'Can you relocate?');
        $tools = $this->question('multiple_choice', 'Which tools?', ['Git', 'Docker']);
        $this->setQuestions($this->job, [['question_id' => $relocate], ['question_id' => $tools]]);

        $this->firstId = (int) $this->apply($this->job, ['answers' => [
            ['question_id' => $relocate, 'value' => true],
            ['question_id' => $tools, 'value' => [$this->optionId($tools, 'Git'), $this->optionId($tools, 'Docker')]],
        ]])->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $budiToken = $this->tokenFor($this->seeker('budi.s@example.test', name: 'Budi Santoso'));
        $budiResume = $this->completeProfile($budiToken);
        $this->secondId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $budiResume], $budiToken)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function export(array|string $query = '', ?string $token = null, ?int $job = null): TestResponse
    {
        $query = is_array($query) ? '?'.http_build_query($query) : $query;

        return $this->api('GET', '/company/jobs/'.($job ?? $this->job).'/applications/export'.$query, token: $token ?? $this->adminToken);
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

    private function audit(): array
    {
        return DB::table('platform_audit_logs')->where('action', 'export.applicants')->orderBy('id')->get()->all();
    }

    public function test_the_export_lists_every_applicant_newest_first_with_the_screening_answers(): void
    {
        $this->api('PUT', "/company/applications/{$this->firstId}/rating", ['stars' => 4], $this->recruiterToken)->assertOk();
        $this->api('PUT', "/company/applications/{$this->firstId}/rating", ['stars' => 2], $this->adminToken)->assertOk();

        $response = $this->export()->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('applicants-job-'.$this->job.'-20261005.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $lines = $this->lines($response);
        $this->assertCount(3, $lines);
        $this->assertSame([
            'Application ID', 'Applied at', 'Name', 'Email', 'Phone', 'City', 'Age', 'Headline', 'Latest job title', 'Latest job company',
            'Expected salary (IDR)', 'Expected salary period', 'Stage', 'Meets must-have', 'Average rating', 'Rating count', 'Interview score', 'Interview yes', 'Interview no', 'Interview kit', 'Can you relocate?', 'Which tools?',
        ], $lines[0]);

        $this->assertSame([(string) $this->secondId, '2026-10-05T10:00:00Z', 'Budi Santoso', 'budi.s@example.test'], array_slice($lines[1], 0, 4));
        $this->assertSame('', $lines[1][20]);

        $first = $lines[2];
        $this->assertSame([(string) $this->firstId, '2026-10-05T09:00:00Z', 'Siti Rahma', 'siti@example.test'], array_slice($first, 0, 4));
        $this->assertSame(['Dilamar', '3', '2'], [$first[12], $first[14], $first[15]]);
        $this->assertSame(['', '', '', ''], array_slice($first, 16, 4));
        $this->assertSame(['yes', 'Git; Docker'], array_slice($first, 20));
    }

    public function test_the_filters_of_the_list_narrow_the_export(): void
    {
        $this->assertCount(3, $this->lines($this->export(['filter' => ['stage_type' => 'applied']])));
        $this->assertCount(1, $this->lines($this->export(['filter' => ['stage_type' => 'offer']])));

        $named = $this->lines($this->export(['filter' => ['q' => 'budi']]));
        $this->assertCount(2, $named);
        $this->assertSame('Budi Santoso', $named[1][2]);

        $oldestFirst = $this->lines($this->export(['sort' => 'applied_at']));
        $this->assertSame([(string) $this->firstId, (string) $this->secondId], [$oldestFirst[1][0], $oldestFirst[2][0]]);

        $this->export(['filter' => ['salary' => '1']])->assertStatus(422);
        $this->export(['filter' => ['stage_type' => 'nope']])->assertStatus(422);
    }

    public function test_an_applicants_text_cannot_run_as_a_spreadsheet_formula_and_special_characters_are_quoted(): void
    {
        $snapshot = json_decode((string) DB::table('recruitment_applications')->where('id', $this->firstId)->value('profile_snapshot'), true);
        $snapshot['name'] = '=HYPERLINK("http://evil.test","click")';
        $snapshot['headline'] = "Line one\nLine \"two\", with a comma";
        DB::table('recruitment_applications')->where('id', $this->firstId)->update(['profile_snapshot' => json_encode($snapshot)]);

        $lines = $this->lines($this->export(['sort' => 'applied_at']));

        $this->assertSame('\'=HYPERLINK("http://evil.test","click")', $lines[1][2]);
        $this->assertSame("Line one\nLine \"two\", with a comma", $lines[1][7]);
    }

    public function test_an_anonymised_applicant_keeps_a_row_without_personal_data(): void
    {
        DB::table('recruitment_applications')->where('id', $this->firstId)->update(['profile_snapshot' => null]);

        $row = $this->lines($this->export(['sort' => 'applied_at']))[1];

        $this->assertSame([(string) $this->firstId, '2026-10-05T09:00:00Z', 'Deleted account'], array_slice($row, 0, 3));
        $this->assertSame(array_fill(0, 8, ''), array_slice($row, 3, 8));
        $this->assertSame('Dilamar', $row[12]);
    }

    public function test_every_export_is_audited_without_personal_data(): void
    {
        $this->export(['filter' => ['stage_type' => 'applied', 'q' => 'budi']])->assertOk();

        $entries = $this->audit();
        $this->assertCount(1, $entries);
        $this->assertSame($this->adminId, (int) $entries[0]->actor_id);
        $this->assertSame('job', $entries[0]->subject_type);
        $this->assertSame($this->job, (int) $entries[0]->subject_id);
        $this->assertEquals(['filters' => ['stage_type' => 'applied'], 'row_count' => 1, 'company_id' => $this->companyId], json_decode($entries[0]->metadata, true));
        $this->assertStringNotContainsString('budi', strtolower($entries[0]->metadata));

        $this->export()->assertOk();
        $this->assertCount(2, $this->audit());
    }

    public function test_an_export_that_cannot_be_audited_is_not_sent(): void
    {
        $this->mock(AuditLogRepository::class, fn ($mock) => $mock->shouldReceive('append')->andThrow(new RuntimeException('audit store is down')));

        $response = $this->export();

        $response->assertStatus(500);
        $this->assertStringNotContainsString('siti@example.test', $response->getContent());
    }

    public function test_only_the_members_who_may_see_the_job_can_export(): void
    {
        $this->export(token: $this->recruiterToken)->assertOk();
        $this->export(token: $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        foreach ([$this->secondRecruiterToken, $this->otherAdminToken] as $token) {
            $this->export(token: $token)->assertStatus(404);
        }

        $this->export(job: 999999)->assertStatus(404);
        $this->assertCount(1, $this->audit());
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
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.max_export_rows', 1, null);

        $this->export()->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:export-too-large')->assertJsonPath('max_rows', 1);
        $this->assertCount(0, $this->audit());

        $lines = $this->lines($this->export(['filter' => ['stage_type' => 'applied', 'q' => 'budi']])->assertOk());
        $this->assertCount(2, $lines);
        $this->assertCount(1, $this->audit());
    }
}
