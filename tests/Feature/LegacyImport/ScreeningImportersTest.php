<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class ScreeningImportersTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $owner;

    private int $company;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->setUpLegacyDatabase();
        $this->locationChain();
        $this->industryId();
        $this->organizationTypeId();
        $this->legacyMasterItem('industry_types', 1, ['en' => 'technology']);
        $this->legacyMasterItem('organization_types', 1, ['en' => 'private-company']);

        $this->owner = $this->legacyUser(['name' => 'PT Maju Jaya', 'role' => 'company']);
        $this->company = $this->legacyCompany($this->owner, 1, 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Runs the importers up to `$entity`, writing, and returns the report of that entity. */
    private function import(string $entity = 'job-questions', bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies', 'jobs', 'questions'] as $step) {
            if ($step !== $entity) {
                $runner->run($step, true);
            }
        }

        return $runner->run($entity, $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report, string $name): array
    {
        return $report->toArray(true)['entities'][$name];
    }

    /** @return list<string> */
    private function skipReasons(ImportReport $report, string $name): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report, $name)['skipped_rows']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function question(string $title, array $attributes = []): int
    {
        return $this->legacyRow('company_questions', ['company_id' => $this->company, 'title' => $title, 'reuse' => 1, 'required' => 0, 'created_at' => '2026-08-01 08:00:00', 'updated_at' => '2026-08-02 09:00:00', ...$attributes]);
    }

    private function job(string $title = 'Backend Developer'): int
    {
        return $this->legacyRow('jobs', ['company_id' => $this->company, 'title' => $title, 'status' => 'pending', 'apply_on' => 'app', 'created_at' => '2026-08-01 08:00:00']);
    }

    private function link(int $job, ?int $question, string $at = '2026-08-03 10:00:00'): int
    {
        return $this->legacyRow('company_question_job', ['job_id' => $job, 'company_question_id' => $question, 'created_at' => $at]);
    }

    // ----- the library -----

    public function test_a_question_becomes_a_text_question_of_the_library_with_the_owner_as_creator(): void
    {
        $legacy = $this->question('  Why do   you want this job?  ');

        $report = $this->import('questions');

        $row = DB::table('recruitment_screening_questions')->first();
        $companyId = (int) DB::table('company_companies')->value('id');
        $ownerId = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $this->owner)->value('new_id');
        $this->assertSame([$companyId, 'text', 'Why do you want this job?', null, $ownerId], [(int) $row->company_id, $row->type, $row->text, $row->archived_at, (int) $row->created_by_user_id]);
        $this->assertSame(['2026-08-01 08:00:00', '2026-08-02 09:00:00'], [$row->created_at, $row->updated_at]);
        $this->assertSame((int) $row->id, (int) DB::table('legacyimport_id_map')->where('entity', 'question')->where('legacy_id', $legacy)->value('new_id'));
        $this->assertSame(0, DB::table('recruitment_screening_question_options')->count());
        $this->assertSame([1, 1, 0], [$this->entity($report, 'questions')['read'], $this->entity($report, 'questions')['imported'], $this->entity($report, 'questions')['skipped']]);
    }

    public function test_a_single_use_question_is_archived_at_once(): void
    {
        $this->question('Reusable question?', ['reuse' => 1]);
        $this->question('Single use question?', ['reuse' => 0]);

        $report = $this->import('questions');

        $this->assertSame([null, '2026-10-07 09:00:00'], DB::table('recruitment_screening_questions')->orderBy('id')->pluck('archived_at')->all());
        $this->assertSame(1, $this->entity($report, 'questions')['notes']['archived-single-use']);
    }

    public function test_questions_that_cannot_be_imported_are_skipped_with_a_reason(): void
    {
        $other = $this->legacyUser(['name' => 'PT Tanpa Industri', 'role' => 'company']);
        $unmatched = $this->legacyCompany($other, 99, 1);
        $this->question('Abcd');
        $this->question('   ');
        $this->question('A question of a company that was skipped', ['company_id' => $unmatched]);
        $this->question('Fine question?');

        $report = $this->import('questions');

        $this->assertSame(['text-too-short', 'text-too-short', 'company-not-imported'], $this->skipReasons($report, 'questions'));
        $this->assertSame(['Fine question?'], DB::table('recruitment_screening_questions')->pluck('text')->all());
    }

    public function test_questions_are_not_deduplicated_and_a_second_run_adds_nothing(): void
    {
        $this->question('Same question?');
        $this->question('Same question?');
        $this->import('questions');

        $report = $this->import('questions');

        $this->assertSame(2, DB::table('recruitment_screening_questions')->count());
        $this->assertSame([2, 0], [$this->entity($report, 'questions')['read'], $this->entity($report, 'questions')['imported']]);
        $this->assertSame(2, $this->entity($report, 'questions')['notes']['already-imported']);
    }

    public function test_a_dry_run_writes_no_question(): void
    {
        $this->question('Dry question?');

        $report = $this->import('questions', false);

        $this->assertSame(0, DB::table('recruitment_screening_questions')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->where('entity', 'question')->count());
        $this->assertSame(1, $this->entity($report, 'questions')['imported']);
    }

    // ----- the attachment to jobs -----

    public function test_questions_are_attached_to_jobs_in_the_legacy_order_with_the_required_flag(): void
    {
        $job = $this->job();
        $first = $this->question('First question?', ['required' => 1]);
        $second = $this->question('Second question?', ['required' => 0]);
        $this->link($job, $second);
        $this->link($job, $first);

        $report = $this->import();

        $newJob = (int) DB::table('job_jobs')->value('id');
        $rows = DB::table('recruitment_job_questions')->where('job_id', $newJob)->orderBy('position')->get();
        $texts = DB::table('recruitment_screening_questions')->pluck('text', 'id')->all();
        $this->assertSame(['Second question?', 'First question?'], $rows->map(fn (object $row): string => $texts[$row->question_id])->all());
        $this->assertSame([1, 2], $rows->pluck('position')->map(fn ($position): int => (int) $position)->all());
        $this->assertSame([0, 1], $rows->pluck('is_required')->map(fn ($flag): int => (int) $flag)->all());
        $this->assertSame([0, 0], $rows->pluck('is_must_have')->map(fn ($flag): int => (int) $flag)->all());
        $this->assertSame([null, null], $rows->pluck('ideal_yes')->all());
        $this->assertSame(0, DB::table('recruitment_job_question_ideal_options')->count());
        $this->assertSame('2026-08-03 10:00:00', $rows[0]->created_at);
        $this->assertSame(2, DB::table('legacyimport_id_map')->where('entity', 'job-question')->count());
        $this->assertSame([2, 2], [$this->entity($report, 'job-questions')['read'], $this->entity($report, 'job-questions')['imported']]);
    }

    public function test_an_imported_attachment_is_served_by_the_company_api(): void
    {
        $job = $this->job();
        $this->link($job, $this->question('Can you start next month?', ['required' => 1]));
        $this->import();

        $newJob = (int) DB::table('job_jobs')->value('id');
        $ownerId = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $this->owner)->value('new_id');
        $token = UserModel::query()->findOrFail($ownerId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->getJson('/api/v1/company/jobs/'.$newJob.'/screening-questions');

        $response->assertOk();
        $this->assertSame('Can you start next month?', $response->json('data.0.text'));
        $this->assertTrue($response->json('data.0.required'));
        $this->assertFalse($response->json('data.0.must_have'));
    }

    public function test_a_single_use_question_stays_attached_to_its_job(): void
    {
        $this->link($this->job(), $this->question('Only for this job?', ['reuse' => 0]));

        $this->import();

        $this->assertSame(1, DB::table('recruitment_job_questions')->count());
        $this->assertNotNull(DB::table('recruitment_screening_questions')->value('archived_at'));
    }

    public function test_links_that_cannot_be_imported_are_skipped_with_a_reason(): void
    {
        $other = $this->legacyUser(['name' => 'PT Lain', 'role' => 'company']);
        $otherCompany = $this->legacyCompany($other, 1, 1);
        $job = $this->job();
        $question = $this->question('Good question?');
        $foreign = $this->question('Question of another company?', ['company_id' => $otherCompany]);
        $short = $this->question('Abcd');
        $skippedJob = $this->legacyRow('jobs', ['company_id' => null, 'title' => 'Admin job', 'status' => 'pending', 'apply_on' => 'app']);

        $this->link($job, null);
        $this->link($skippedJob, $question);
        $this->link($job, $short);
        $this->link($job, $foreign);
        $this->link($job, $question);
        $this->link($job, $question);

        $report = $this->import();

        $this->assertSame(['link-incomplete', 'job-not-imported', 'question-not-imported', 'company-mismatch', 'duplicate-link'], $this->skipReasons($report, 'job-questions'));
        $this->assertSame(1, DB::table('recruitment_job_questions')->count());
    }

    public function test_a_job_with_more_questions_than_the_limit_is_imported_whole_and_counted(): void
    {
        $job = $this->job();

        for ($i = 1; $i <= 7; $i++) {
            $this->link($job, $this->question("Question number {$i}?"));
        }

        $report = $this->import();

        $this->assertSame(7, DB::table('recruitment_job_questions')->count());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], DB::table('recruitment_job_questions')->orderBy('position')->pluck('position')->map(fn ($position): int => (int) $position)->all());
        $this->assertSame(1, $this->entity($report, 'job-questions')['notes']['job-over-question-limit']);
    }

    public function test_a_second_run_adds_nothing_and_a_later_link_continues_the_order(): void
    {
        $job = $this->job();
        $this->link($job, $this->question('First question?'));
        $this->import();
        $this->link($job, $this->question('Later question?'));

        $report = $this->import();

        $this->assertSame([1, 2], DB::table('recruitment_job_questions')->orderBy('position')->pluck('position')->map(fn ($position): int => (int) $position)->all());
        $this->assertSame([2, 1], [$this->entity($report, 'job-questions')['read'], $this->entity($report, 'job-questions')['imported']]);
    }

    public function test_a_dry_run_attaches_nothing(): void
    {
        $this->link($this->job(), $this->question('Dry question?'));

        $report = $this->import('job-questions', false);

        $this->assertSame(0, DB::table('recruitment_job_questions')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->where('entity', 'job-question')->count());
        $this->assertSame(1, $this->entity($report, 'job-questions')['imported']);
    }
}
