<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Assessment\Application\EraseAssessmentData;
use Src\Assessment\Domain\ReportRepository;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * Phase 6, part 7: a platform admin allows the automatic issue of reports per provider, a psychologist turns it on per battery and names the responsible psychologist,
 * the report is issued at once when the attempt is submitted (and falls back to the manual queue when anything stands in the way), and a psychologist marks it
 * reviewed or issues a revision (`-R1`, `-R2`). The clock is frozen at 09:00 on 2026-10-05.
 */
final class AutoIssueTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private const string EXPLANATION = 'Dear {{candidate_name}}, about {{test_title}}: {{scale:extraversion:explanation}} (ref. {{report_number}}, {{psychologist_name}}, {{signed_on}}).';

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

    /** @return array{0: int, 1: string} the id of a second psychologist of the first provider, and their token */
    private function secondPsychologist(): array
    {
        $user = UserModel::query()->create(['type' => 'psychologist', 'name' => 'Psikolog Kedua', 'email' => 'kedua@example.test', 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00']);
        $id = (int) DB::table('assessment_psychologists')->insertGetId([
            'user_id' => $user->id, 'provider_id' => $this->providerId, 'str_number' => 'STR-2', 'str_valid_until' => '2030-01-31',
            'silp_number' => 'SIPP-2', 'silp_valid_until' => '2030-01-31', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id, $user->createToken('provider', ['*'], now()->addHour())->plainTextToken];
    }

    /** A battery of one Likert section (no drawing) with norms that explain themselves, a report template and an explanation template; published, granted to the company. */
    private function battery(?string $explanationTemplate = self::EXPLANATION, bool $explainNorms = true): int
    {
        $id = $this->newTest('provider', ['title' => 'Personality']);

        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [
            ['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 2, 'standard_score' => 40, 'band' => 'Low', 'explanation' => $explainNorms ? 'You prefer quiet settings.' : null],
            ['scale_key' => 'extraversion', 'raw_min' => 3, 'raw_max' => 10, 'standard_score' => 60, 'band' => 'High', 'explanation' => $explainNorms ? 'You like people and often seek company.' : null],
        ]])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Dear {{candidate_name}}: {{test_title}} - extraversion {{scale:extraversion:raw}} ({{scale:extraversion:band}}). No. {{report_number}} by {{psychologist_name}} on {{signed_on}}.'])->assertOk();

        if ($explanationTemplate !== null) {
            $this->provider('PUT', "tests/{$id}/explanation-template", ['body' => $explanationTemplate])->assertOk();
        }

        $this->provider('POST', "tests/{$id}/publish")->assertOk();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        return $id;
    }

    private function allow(bool $allowed = true, ?int $providerId = null): TestResponse
    {
        return $this->assessmentAdmin('PUT', 'providers/'.($providerId ?? $this->providerId).'/auto-issue', ['allowed' => $allowed]);
    }

    private function enable(int $battery, ?int $psychologist = null): TestResponse
    {
        return $this->provider('PUT', "tests/{$battery}/auto-issue", ['enabled' => true, 'responsible_psychologist_id' => $psychologist ?? $this->psychologistId()]);
    }

    /** The provider allows it and the battery is set up to issue at once. */
    private function readyBattery(): int
    {
        $this->allow()->assertOk();
        $battery = $this->battery();
        $this->enable($battery)->assertOk();

        return $battery;
    }

    /** The candidate takes the battery ("Always": raw score 3) and hands it in. Returns the report id. */
    private function takeBattery(int $battery, int $tabSwitches = 0, ?callable $afterInvitation = null): int
    {
        $due = Carbon::now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $battery, 'due_at' => $due]], $this->recruiterToken)->assertOk();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');

        if ($afterInvitation !== null) {
            $afterInvitation();
        }

        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        $likert = (int) $this->take('GET', 'attempt')->json('data.questions.0.id');
        $this->take('PUT', "attempt/questions/{$likert}/answer", ['option_positions' => [3]])->assertNoContent();

        foreach (range(1, $tabSwitches) as $ignored) {
            if ($tabSwitches > 0) {
                $this->take('POST', 'attempt/integrity-events', ['type' => 'tab_switch'])->assertNoContent();
            }
        }

        $this->take('POST', 'attempt/submit')->assertOk();

        return (int) DB::table('assessment_reports')->value('id');
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return (array) DB::table('assessment_reports')->first();
    }

    // ----- the platform admin's gate -----

    public function test_a_platform_admin_allows_a_provider_to_issue_automatically_and_it_is_audited(): void
    {
        $this->assessmentAdmin('GET', "providers/{$this->providerId}")->assertOk()->assertJsonPath('data.auto_issue_allowed', false);

        $this->allow()->assertOk()->assertJsonPath('data.auto_issue_allowed', true);
        $this->allow()->assertOk();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'assessment_provider.auto_issue_changed')->count(), 'Allowing twice changes nothing and is audited once.');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment_provider.auto_issue_changed', 'actor_type' => 'admin', 'actor_id' => $this->assessmentAdminId, 'subject_id' => $this->providerId]);

        $this->allow(false)->assertOk()->assertJsonPath('data.auto_issue_allowed', false);
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'assessment_provider.auto_issue_changed')->count());

        $this->assessmentAdmin('PUT', "providers/{$this->providerId}/auto-issue", [])->assertStatus(422);
        $this->allow(true, 999999)->assertNotFound();

        $limited = AdminModel::query()->create(['name' => 'Library only', 'email' => 'library@example.test', 'password' => 'hash', 'status' => 'active']);
        $limited->givePermissionTo(['assessment_library.manage']);
        $this->assessmentAdmin('PUT', "providers/{$this->providerId}/auto-issue", ['allowed' => true], $limited->createToken('console', ['*'], now()->addHour())->plainTextToken)->assertForbidden();
    }

    // ----- the battery's setting -----

    public function test_a_battery_turns_it_on_only_when_the_provider_allows_it_with_a_responsible_psychologist_of_the_provider(): void
    {
        $battery = $this->battery();

        $this->enable($battery)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:auto-issue-not-allowed');

        $this->allow()->assertOk();
        $this->provider('PUT', "tests/{$battery}/auto-issue", ['enabled' => true])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-psychologist');
        $this->enable($battery, $this->psychologistId($this->otherProviderId))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-psychologist');
        $this->enable($battery, 999999)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-psychologist');

        $this->enable($battery)->assertOk()->assertJsonPath('data.auto_issue', true)->assertJsonPath('data.responsible_psychologist_id', $this->psychologistId());
        $this->provider('PUT', "tests/{$battery}/auto-issue", ['enabled' => false])->assertOk()->assertJsonPath('data.auto_issue', false)->assertJsonPath('data.responsible_psychologist_id', null);

        $this->otherProviderAttempt($battery);
    }

    private function otherProviderAttempt(int $battery): void
    {
        $this->api('PUT', "/provider/tests/{$battery}/auto-issue", ['enabled' => true, 'responsible_psychologist_id' => $this->psychologistId()], $this->otherPsychologistToken)->assertNotFound();
        $this->company('PUT', "tests/{$battery}/auto-issue", ['enabled' => false])->assertStatus(404);
    }

    public function test_a_published_battery_that_is_not_ready_lists_what_is_missing(): void
    {
        $this->allow()->assertOk();
        $battery = $this->battery(explanationTemplate: null, explainNorms: false);

        $problems = $this->enable($battery)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:test-incomplete')->json('errors.test');
        $this->assertContains('The battery needs an explanation template.', $problems);
        $this->assertContains('The norm of the scale extraversion from 0 to 2 has no explanation.', $problems);
        $this->assertContains('The norm of the scale extraversion from 3 to 10 has no explanation.', $problems);
        $this->assertFalse((bool) DB::table('assessment_tests')->where('id', $battery)->value('auto_issue'));
    }

    public function test_automatic_issue_needs_a_norm_row_without_a_group_for_every_scale(): void
    {
        $this->allow()->assertOk();
        $battery = $this->battery();
        $this->provider('PUT', "tests/{$battery}/norms", ['norms' => [
            ['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 50, 'band' => 'Mid', 'explanation' => 'Balanced.', 'age_min' => 18, 'age_max' => 25],
        ]])->assertOk();

        $problems = $this->enable($battery)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:test-incomplete')->json('errors.test');
        $this->assertContains('The scale extraversion needs a norm row without an age or education group, so that no candidate falls through.', $problems);
    }

    public function test_a_battery_with_a_drawing_question_cannot_issue_automatically(): void
    {
        $this->allow()->assertOk();
        $battery = $this->battery();
        $this->provider('POST', "tests/{$battery}/clone")->assertCreated();
        $copy = (int) DB::table('assessment_tests')->orderByDesc('id')->value('id');
        $drawing = (int) $this->provider('POST', "tests/{$copy}/sections", ['title' => 'Draw a tree', 'format' => 'drawing', 'duration_seconds' => 900])->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$drawing}/questions", ['type' => 'drawing', 'text' => 'Draw a tree.', 'points' => 10])->assertCreated();
        $this->provider('POST', "tests/{$copy}/publish")->assertOk();

        $this->enable($copy)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:test-incomplete')
            ->assertJsonFragment(['A battery with a drawing question cannot issue reports automatically: a psychologist scores drawings.']);
    }

    public function test_publishing_a_draft_with_the_setting_on_checks_what_an_automatic_report_needs(): void
    {
        $this->allow()->assertOk();
        $id = $this->newTest('provider', ['title' => 'Personality']);
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 50, 'band' => 'Mid']]])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Report for {{candidate_name}}'])->assertOk();

        $this->enable($id)->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:test-incomplete')
            ->assertJsonFragment(['The battery needs an explanation template.']);

        $this->provider('PUT', "tests/{$id}/explanation-template", ['body' => 'Hello {{candidate_name}}'])->assertOk();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 50, 'band' => 'Mid', 'explanation' => 'Balanced.']]])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk()->assertJsonPath('data.auto_issue', true);
    }

    public function test_the_explanation_template_knows_the_explanation_placeholder_but_the_report_template_does_not(): void
    {
        $battery = $this->newTest('provider', ['title' => 'Personality']);
        $section = (int) $this->provider('POST', "tests/{$battery}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();

        $this->provider('PUT', "tests/{$battery}/report-template", ['body' => '{{scale:extraversion:explanation}}'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report-template');
        $this->provider('PUT', "tests/{$battery}/explanation-template", ['body' => '{{scale:other:explanation}}'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report-template');
        $this->provider('PUT', "tests/{$battery}/explanation-template", ['body' => '{{unknown}}'])->assertStatus(422);
        $this->provider('GET', "tests/{$battery}/explanation-template")->assertOk()->assertJsonPath('data.body', null);
        $this->provider('PUT', "tests/{$battery}/explanation-template", ['body' => '{{scale:extraversion:explanation}}'])->assertOk()->assertJsonPath('data.body', '{{scale:extraversion:explanation}}');
        $this->provider('GET', "tests/{$battery}/explanation-template")->assertOk()->assertJsonPath('data.body', '{{scale:extraversion:explanation}}');
        $this->provider('GET', "tests/{$battery}/report-template")->assertOk()->assertJsonPath('data.body', null);
    }

    // ----- issuing at once -----

    public function test_a_submitted_attempt_issues_its_report_at_once_under_the_responsible_psychologist(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);

        $report = $this->report();
        $this->assertSame($reportId, (int) $report['id']);
        $this->assertSame(['signed', true, 'LEMB/2026/00001', 0], [$report['status'], (bool) $report['auto_issued'], $report['report_number'], (int) $report['revision']]);
        $this->assertSame(['Psikolog Lembaga Psikologi Contoh', 'STR-1', 'SIPP-1'], [$report['signer_name'], $report['signer_str_number'], $report['signer_silp_number']]);
        $this->assertSame($this->psychologistId(), (int) $report['signed_by_psychologist_id']);
        $this->assertNotNull($report['signed_at']);
        $this->assertNull($report['reviewed_at']);
        $this->assertStringContainsString('No. LEMB/2026/00001 by Psikolog Lembaga Psikologi Contoh on 2026-10-05.', $report['body']);
        $this->assertSame(
            'Dear Siti Rahma, about Personality: You like people and often seek company. (ref. LEMB/2026/00001, Psikolog Lembaga Psikologi Contoh, 2026-10-05).',
            $report['explanation'],
        );

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.report_auto_issued', 'actor_type' => 'system', 'subject_type' => 'assessment_report', 'subject_id' => $reportId]);
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'assessment.report_signed')->count());
        $this->assertSame(['assessment.report_signed'], DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->recruiterId)->where('type', 'assessment.report_signed')->pluck('type')->all());
        $this->assertSame(['assessment.explanation_ready'], DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->seekerId)->where('type', 'assessment.explanation_ready')->pluck('type')->all());
    }

    public function test_the_company_and_the_candidate_read_an_automatically_issued_report(): void
    {
        $battery = $this->readyBattery();
        $this->takeBattery($battery);

        $this->company('GET', "invitations/{$this->invitationId}/result")->assertOk()
            ->assertJsonPath('data.result.status', 'signed')
            ->assertJsonPath('data.result.report.number', 'LEMB/2026/00001');

        $pdf = $this->company('GET', "invitations/{$this->invitationId}/report.pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $this->api('GET', "/me/assessment/invitations/{$this->invitationId}/explanation", [], $this->seekerToken)->assertOk();
    }

    public function test_the_automatic_report_waits_in_the_queue_until_a_psychologist_reviews_it(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);

        $this->provider('GET', 'reviews')->assertOk()->assertJsonPath('data.0.auto_issued', true)->assertJsonPath('data.0.reviewed', false);
        $this->provider('GET', 'reviews?filter[reviewed]=0')->assertOk()->assertJsonCount(1, 'data');
        $this->provider('GET', 'reviews?filter[reviewed]=1')->assertOk()->assertJsonCount(0, 'data');
        $this->provider('GET', "reviews/{$reportId}")->assertOk()->assertJsonPath('data.auto_issued', true)->assertJsonPath('data.reviewed', false)->assertJsonPath('data.signing_problems', []);

        $this->provider('POST', "reviews/{$reportId}/mark-reviewed")->assertOk()->assertJsonPath('data.reviewed', true)->assertJsonPath('data.auto_issued', true);
        $first = DB::table('assessment_reports')->value('reviewed_at');
        $this->assertNotNull($first);
        $this->assertSame($this->psychologistId(), (int) DB::table('assessment_reports')->value('reviewed_by_psychologist_id'));

        Carbon::setTestNow(now()->addMinutes(30));
        $this->provider('POST', "reviews/{$reportId}/mark-reviewed")->assertOk();
        $this->assertSame($first, DB::table('assessment_reports')->value('reviewed_at'), 'Marking it again changes nothing.');

        $this->provider('GET', 'reviews?filter[reviewed]=1')->assertOk()->assertJsonCount(1, 'data');
        $this->provider('GET', 'reviews?filter[reviewed]=0')->assertOk()->assertJsonCount(0, 'data');
    }

    // ----- falling back to the manual queue -----

    /** @return array<string, array{0: string, 1: callable(self): void}> */
    public static function stoppers(): array
    {
        return [
            'the provider no longer allows it' => ['provider', static fn (self $test) => $test->allow(false)->assertOk()],
            'the provider is suspended' => ['provider', static fn () => DB::table('assessment_providers')->update(['status' => 'suspended'])],
            'the responsible psychologist is suspended' => ['psychologist', static fn () => DB::table('assessment_psychologists')->update(['status' => 'suspended'])],
            'the STR of the responsible psychologist expired' => ['psychologist', static fn () => DB::table('assessment_psychologists')->update(['str_valid_until' => '2026-10-04'])],
            'the SILP of the responsible psychologist expired' => ['psychologist', static fn () => DB::table('assessment_psychologists')->update(['silp_valid_until' => '2026-10-04'])],
            'a norm lost its explanation' => ['norms', static fn () => DB::table('assessment_norms')->update(['explanation' => null])],
            'the explanation template is gone' => ['template', static fn () => DB::table('assessment_report_templates')->update(['explanation_template' => null])],
        ];
    }

    #[DataProvider('stoppers')]
    public function test_the_report_stays_a_draft_for_a_psychologist_when_something_stands_in_the_way(string $label, callable $stopper): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery, afterInvitation: fn () => $stopper($this));

        $report = $this->report();
        $this->assertSame($reportId, (int) $report['id']);
        $this->assertSame(['pending_review', false, null, null], [$report['status'], (bool) $report['auto_issued'], $report['report_number'], $report['explanation']], $label);
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'assessment.report_auto_issued')->count());
        $this->assertSame([], DB::table('notifications')->where('type', 'assessment.explanation_ready')->pluck('type')->all());
    }

    public function test_an_attempt_with_a_major_integrity_tier_is_left_for_a_psychologist(): void
    {
        $battery = $this->readyBattery();
        $this->takeBattery($battery, tabSwitches: 5);

        $this->assertSame(['pending_review', false], [$this->report()['status'], (bool) $this->report()['auto_issued']]);
    }

    public function test_a_battery_with_the_setting_off_is_left_for_a_psychologist(): void
    {
        $this->allow()->assertOk();
        $battery = $this->battery();

        $this->takeBattery($battery);

        $this->assertSame('pending_review', $this->report()['status']);
    }

    public function test_the_numbers_of_automatic_and_manual_reports_follow_each_other(): void
    {
        $battery = $this->readyBattery();
        $this->takeBattery($battery);

        $this->assertSame('LEMB/2026/00001', $this->report()['report_number']);
        $this->assertSame(2, $this->app->make(ReportRepository::class)->nextSequence($this->providerId, 2026));
    }

    // ----- marking a manual report and revising -----

    public function test_a_report_signed_by_hand_is_neither_marked_nor_revised(): void
    {
        $this->allow()->assertOk();
        $battery = $this->battery();
        $reportId = $this->takeBattery($battery);
        $this->provider('PUT', "reviews/{$reportId}", ['body' => (string) $this->report()['body'], 'explanation' => 'You like people.', 'review_notes' => null])->assertOk();
        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $this->provider('POST', "reviews/{$reportId}/mark-reviewed")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:report-not-auto-issued');
        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => 'New', 'explanation' => 'New'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:report-not-auto-issued');
        $this->provider('GET', 'reviews?filter[reviewed]=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reviewed', true);
    }

    public function test_a_draft_cannot_be_marked_or_revised(): void
    {
        $this->allow()->assertOk();
        $battery = $this->battery();
        $reportId = $this->takeBattery($battery);

        $this->provider('POST', "reviews/{$reportId}/mark-reviewed")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:report-not-auto-issued');
        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => 'New', 'explanation' => 'New'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:report-not-auto-issued');
    }

    public function test_a_revision_keeps_the_number_with_a_suffix_and_the_earlier_version(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);
        $original = $this->report();
        [$secondId, $secondToken] = $this->secondPsychologist();

        Carbon::setTestNow(now()->addMinutes(30));
        $this->api('POST', "/provider/reviews/{$reportId}/revise", ['body' => 'Revised text for {{candidate_name}}. Ref {{report_number}} by {{psychologist_name}}.', 'explanation' => 'Revised explanation.'], $secondToken)->assertOk()
            ->assertJsonPath('data.report_number', 'LEMB/2026/00001-R1')
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.reviewed', true)
            ->assertJsonPath('data.signer_name', 'Psikolog Kedua')
            ->assertJsonPath('data.explanation', 'Revised explanation.');

        $report = $this->report();
        $this->assertSame(['signed', 'LEMB/2026/00001-R1', 1, 2026, 1], [$report['status'], $report['report_number'], (int) $report['revision'], (int) $report['report_year'], (int) $report['report_sequence']]);
        $this->assertSame(['STR-2', 'SIPP-2', $secondId, $secondId], [$report['signer_str_number'], $report['signer_silp_number'], (int) $report['signed_by_psychologist_id'], (int) $report['reviewed_by_psychologist_id']]);
        $this->assertStringContainsString('Ref LEMB/2026/00001-R1 by Psikolog Kedua.', $report['body']);

        $kept = (array) DB::table('assessment_report_revisions')->first();
        $this->assertSame([$reportId, 0, 'LEMB/2026/00001', $original['body'], $original['explanation'], 'STR-1'], [(int) $kept['report_id'], (int) $kept['revision'], $kept['report_number'], $kept['body'], $kept['explanation'], $kept['signer_str_number']]);
        $this->assertSame('2026-10-05 09:30:00', $kept['superseded_at']);

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.report_revised', 'actor_type' => 'user', 'subject_id' => $reportId]);
        $this->assertSame(['assessment.report_revised'], DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'assessment.report_revised')->pluck('type')->all());
        $this->assertSame(['assessment.explanation_revised'], DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'assessment.explanation_revised')->pluck('type')->all());

        // A second revision becomes -R2 and keeps the first one too.
        $this->api('POST', "/provider/reviews/{$reportId}/revise", ['body' => 'Second text', 'explanation' => 'Second explanation'], $secondToken)->assertOk()->assertJsonPath('data.report_number', 'LEMB/2026/00001-R2');
        $this->assertSame([0, 1], DB::table('assessment_report_revisions')->orderBy('revision')->pluck('revision')->map(fn ($n): int => (int) $n)->all());
        $this->company('GET', "invitations/{$this->invitationId}/result")->assertOk()->assertJsonPath('data.result.report.number', 'LEMB/2026/00001-R2');
    }

    public function test_a_revision_needs_a_text_an_explanation_and_a_valid_licence(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);

        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => '   ', 'explanation' => 'x'])->assertStatus(422);
        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => 'x', 'explanation' => '   '])->assertStatus(422);
        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => 'x'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');

        DB::table('assessment_psychologists')->update(['silp_valid_until' => '2026-10-04']);
        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => 'x', 'explanation' => 'y'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:cannot-sign-reports');
        $this->provider('POST', "reviews/{$reportId}/mark-reviewed")->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:cannot-sign-reports');

        $this->assertSame(0, DB::table('assessment_report_revisions')->count());
        $this->assertSame('LEMB/2026/00001', $this->report()['report_number']);
    }

    public function test_only_psychologists_of_the_provider_review_or_revise_its_reports(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);

        $this->api('POST', "/provider/reviews/{$reportId}/mark-reviewed", [], $this->otherPsychologistToken)->assertNotFound();
        $this->api('POST', "/provider/reviews/{$reportId}/revise", ['body' => 'x', 'explanation' => 'y'], $this->otherPsychologistToken)->assertNotFound();
        $this->api('POST', "/provider/reviews/{$reportId}/mark-reviewed", [], $this->recruiterToken)->assertForbidden();
        $this->api('POST', "/provider/reviews/{$reportId}/revise", ['body' => 'x', 'explanation' => 'y'], $this->seekerToken)->assertForbidden();
    }

    public function test_erasing_the_candidate_clears_the_text_of_the_report_and_of_every_earlier_version(): void
    {
        $battery = $this->readyBattery();
        $reportId = $this->takeBattery($battery);
        $this->provider('POST', "reviews/{$reportId}/revise", ['body' => 'Revised text', 'explanation' => 'Revised explanation'])->assertOk();
        $this->assertNotSame('', (string) DB::table('assessment_report_revisions')->value('body'));

        $this->app->make(EraseAssessmentData::class)->execute($this->seekerId);

        $kept = (array) DB::table('assessment_report_revisions')->first();
        $this->assertSame(['', null, 'LEMB/2026/00001', 0], [$kept['body'], $kept['explanation'], $kept['report_number'], (int) $kept['revision']], 'The register keeps the number and the signer, not the text.');
        $report = $this->report();
        $this->assertSame(['', null, 'LEMB/2026/00001-R1'], [$report['body'], $report['explanation'], $report['report_number']]);
    }
}
