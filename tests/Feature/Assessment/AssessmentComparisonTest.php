<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-assessment-automation.md` section 3: comparing the assessment results of the candidates of one job. The clock is frozen at 09:00 on 2026-10-05. */
final class AssessmentComparisonTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $job;

    private int $first;

    private int $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->first = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        $token = $this->tokenFor($this->seeker('dua@example.test', name: 'Dua Orang'));
        $this->second = (int) $this->apply($this->job, ['resume_id' => $this->completeProfile($token)], $token)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** A skill test with two sections; returns the test id and the section ids. @return array{0: int, 1: list<int>} */
    private function skillTest(string $title): array
    {
        $testId = $this->newTest('company', ['title' => $title]);
        $sections = [];

        foreach (['Logic', 'Numbers'] as $name) {
            $sectionId = $this->sectionWithQuestion('company', $testId);
            DB::table('assessment_sections')->where('id', $sectionId)->update(['title' => $name]);
            $sections[] = $sectionId;
        }

        return [$testId, $sections];
    }

    /** A submitted attempt of the application, with its result when one is given. @param  array<string, mixed>  $result */
    private function submit(int $applicationId, int $testId, string $at, ?array $result, ?int $stageId = null): int
    {
        $application = DB::table('recruitment_applications')->where('id', $applicationId)->first();

        $invitation = (int) DB::table('assessment_invitations')->insertGetId([
            'application_id' => $applicationId, 'company_id' => $this->companyId, 'job_id' => $this->job, 'applicant_user_id' => $application->user_id,
            'test_id' => $testId, 'invited_by_user_id' => $this->recruiterId, 'starts_at' => '2026-10-01 00:00:00', 'due_at' => '2026-10-20 00:00:00',
            'status' => 'submitted', 'stage_id' => $stageId, 'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00',
        ]);

        $attempt = (int) DB::table('assessment_attempts')->insertGetId([
            'invitation_id' => $invitation, 'consented_at' => $at, 'started_at' => $at, 'section_opened_at' => $at, 'ends_at' => $at, 'submitted_at' => $at,
            'seed' => 1, 'created_at' => $at, 'updated_at' => $at,
        ]);

        if ($result !== null) {
            DB::table('assessment_results')->insert([
                'attempt_id' => $attempt, 'integrity_tier' => 'none', 'section_scores' => '[]', 'created_at' => $at, 'updated_at' => $at, ...$result,
                ...(isset($result['section_scores']) ? ['section_scores' => json_encode($result['section_scores'])] : []),
                ...(isset($result['scale_scores']) ? ['scale_scores' => json_encode($result['scale_scores'])] : []),
            ]);
        }

        return $attempt;
    }

    /** @param  list<int>  $sections */
    private function scored(array $sections, float $logic, float $numbers, float $percentage): array
    {
        return [
            'status' => 'scored', 'percentage' => $percentage, 'band' => 'good', 'earned_points' => $percentage, 'max_points' => 100,
            'section_scores' => [
                ['section_id' => $sections[0], 'format' => 'standard', 'answered' => 1, 'total' => 1, 'earned' => $logic, 'max' => 10],
                ['section_id' => $sections[1], 'format' => 'standard', 'answered' => 1, 'total' => 1, 'earned' => $numbers, 'max' => 10],
            ],
        ];
    }

    private function compare(string $query = '', ?string $token = null): TestResponse
    {
        return $this->api('GET', "/company/jobs/{$this->job}/assessment-results{$query}", token: $token ?? $this->recruiterToken);
    }

    // ----- a skill test -----

    public function test_the_candidates_are_compared_by_percentage_with_a_cell_per_section_and_the_unscored_come_last(): void
    {
        [$test, $sections] = $this->skillTest('Logic and numbers');
        $this->submit($this->first, $test, '2026-10-03 10:00:00', ['status' => 'pending_scoring', ...['section_scores' => []]]);
        $this->submit($this->second, $test, '2026-10-04 10:00:00', $this->scored($sections, 10, 5, 75));

        $response = $this->compare()->assertOk();

        $this->assertSame([$this->second, $this->first], $response->json('data.*.application.id'));
        $this->assertSame(['id' => $test, 'name' => 'Logic and numbers', 'kind' => 'skill'], $response->json('test'));
        $this->assertSame(['Logic', 'Numbers'], $response->json('columns'));
        $this->assertSame([['title' => 'Logic', 'percentage' => 100], ['title' => 'Numbers', 'percentage' => 50]], $response->json('data.0.sections'));
        $this->assertSame(['scored', 75, 'good', 'none'], [$response->json('data.0.status'), $response->json('data.0.percentage'), $response->json('data.0.band'), $response->json('data.0.integrity_tier')]);
        $this->assertSame(['pending_scoring', null], [$response->json('data.1.status'), $response->json('data.1.percentage')]);
        $this->assertSame('Dua Orang', $response->json('data.0.application.name'));
        $this->assertSame($test, $response->json('data.0.invitation.test.id'));

        $this->assertSame([$this->second, $this->first], $this->compare('?sort=submitted_at')->json('data.*.application.id'));
    }

    public function test_the_latest_submitted_test_counts_and_the_filters_choose_another(): void
    {
        [$logic, $logicSections] = $this->skillTest('Logic');
        [$verbal, $verbalSections] = $this->skillTest('Verbal');
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');

        $this->submit($this->first, $logic, '2026-10-02 10:00:00', $this->scored($logicSections, 5, 5, 50), $stage);
        $this->submit($this->first, $verbal, '2026-10-04 10:00:00', $this->scored($verbalSections, 8, 8, 80));
        $this->submit($this->second, $logic, '2026-10-03 10:00:00', $this->scored($logicSections, 6, 6, 60), $stage);

        $default = $this->compare()->assertOk();
        $this->assertSame($logic, $default->json('test.id'), 'Each test is the latest of one row, so the oldest test gives the columns.');
        $this->assertSame([$this->first, $this->second], $default->json('data.*.application.id'));
        $this->assertSame([80, 60], $default->json('data.*.percentage'));
        $this->assertNull($default->json('data.0.sections'), 'The row of another test has no cells.');
        $this->assertNotNull($default->json('data.1.sections'));

        $byTest = $this->compare("?filter[test_id]={$logic}")->assertOk();
        $this->assertSame([60, 50], $byTest->json('data.*.percentage'));
        $this->assertSame([$this->second, $this->first], $byTest->json('data.*.application.id'));

        $byStage = $this->compare("?filter[stage_id]={$stage}")->assertOk();
        $this->assertEqualsCanonicalizing([50, 60], $byStage->json('data.*.percentage'));

        $none = $this->compare('?filter[test_id]=999999')->assertOk();
        $this->assertSame([], $none->json('data'));
        $this->assertNull($none->json('test'));
        $this->assertSame([], $none->json('columns'));
    }

    // ----- a battery -----

    public function test_a_battery_shows_nothing_before_the_signature_and_the_scale_bands_and_the_number_after(): void
    {
        $battery = $this->newTest('provider', ['title' => 'Personality']);
        $this->submit($this->first, $battery, '2026-10-03 10:00:00', ['status' => 'scored', 'scale_scores' => ['extraversion' => 2.0]]);
        $signed = $this->submit($this->second, $battery, '2026-10-04 10:00:00', ['status' => 'scored', 'scale_scores' => ['extraversion' => 3.0]]);
        DB::table('assessment_reports')->insert([
            'attempt_id' => $signed, 'provider_id' => $this->providerId, 'status' => 'signed', 'body' => 'Report', 'report_number' => 'LPC/2026/1', 'report_year' => 2026,
            'report_sequence' => 1, 'signer_name' => 'Dewi', 'signed_at' => '2026-10-04 12:00:00', 'created_at' => '2026-10-04 12:00:00', 'updated_at' => '2026-10-04 12:00:00',
        ]);

        $response = $this->compare()->assertOk();

        $this->assertSame(['id' => $battery, 'name' => 'Personality', 'kind' => 'psychological'], $response->json('test'));
        $this->assertSame(['extraversion'], $response->json('columns'));
        $this->assertSame([$this->second, $this->first], $response->json('data.*.application.id'), 'A battery sorts by the time of submission.');
        $this->assertSame(['signed', [['scale' => 'extraversion', 'band' => null]], 'LPC/2026/1'], [$response->json('data.0.status'), $response->json('data.0.scales'), $response->json('data.0.report_number')]);
        $this->assertSame(['awaiting_psychologist_review', null, null, null, null], [
            $response->json('data.1.status'), $response->json('data.1.scales'), $response->json('data.1.report_number'), $response->json('data.1.percentage'), $response->json('data.1.band'),
        ]);
    }

    // ----- who may -----

    public function test_only_a_member_who_sees_the_job_and_may_send_tests_reads_the_comparison(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson("/api/v1/company/jobs/{$this->job}/assessment-results")->assertUnauthorized();

        $this->compare(token: $this->seekerToken)->assertForbidden();
        $this->compare(token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/jobs/999999/assessment-results', token: $this->recruiterToken)->assertNotFound();
    }

    public function test_an_unknown_filter_or_sort_is_refused(): void
    {
        $this->compare('?filter[name]=x')->assertStatus(422);
        $this->compare('?sort=-percentage')->assertStatus(422);
        $this->compare('?filter[stage_type]=nope')->assertStatus(422);
    }
}
