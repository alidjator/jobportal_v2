<?php

namespace Tests\Feature\Assessment;

use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\NormConverter;
use Src\Assessment\Domain\ReportRenderer;
use Src\Identity\Domain\Events\UserAnonymized;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * Phase 6, part 6: the company reads the results and the integrity log, the assessors score the written answers, a psychologist of the provider reviews, signs and
 * numbers the report of a psychological test, the company downloads the PDF, the candidate reads the explanation and the application anonymisation erases it all.
 * The clock is frozen at 09:00 on 2026-10-05.
 */
final class ResultsAndReportsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $applicationId;

    private int $invitationId = 0;

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

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** @param  array<string, mixed>  $overrides */
    private function invite(int $testId, array $overrides = []): void
    {
        $due = Carbon::now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');
        $assessment = ['test_id' => $testId, 'due_at' => $due, ...$overrides];

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('assessment'), 'assessment' => $assessment], $this->recruiterToken)->assertNoContent();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');
    }

    /** The token of the device that started the attempt, sent with every later call. */
    private ?string $attemptSession = null;

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = []): TestResponse
    {
        $response = $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $this->seekerToken, $this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession]);

        if ($uri === 'start' && is_string($session = $response->json('data.attempt_session'))) {
            $this->attemptSession = $session;
        }

        return $response;
    }

    /** @return list<int> the ids of the questions of the open section */
    private function questionIds(): array
    {
        return array_map('intval', array_column($this->take('GET', 'attempt')->json('data.questions'), 'id'));
    }

    /** @param  array<string, mixed>  $body */
    private function asTeam(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->company($method, "invitations/{$this->invitationId}/{$uri}", $body, $token ?? $this->recruiterToken);
    }

    /** A skill test with one single-choice question (2 points, the first option is right) and one written question (5 points). */
    private function skillTestWithWrittenQuestion(): int
    {
        $id = $this->newTest('company');
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');
        $this->company('POST', "sections/{$sectionId}/questions", $this->choice())->assertCreated();
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Explain a recent project.', 'points' => 5])->assertCreated();
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    /** Sends the written-question test; the recruiter who sends it is its assessor. */
    private function inviteToWrittenTest(): void
    {
        $this->invite($this->skillTestWithWrittenQuestion(), ['assessor_ids' => [$this->recruiterId]]);
    }

    /** Takes the written-question test: the right choice and a written answer. */
    private function takeWrittenTest(): void
    {
        $this->inviteToWrittenTest();
        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        [$choice, $written] = $this->questionIds();
        $this->take('PUT', "attempt/questions/{$choice}/answer", ['option_positions' => [1]])->assertNoContent();
        $this->take('PUT', "attempt/questions/{$written}/answer", ['text' => 'I built a job portal API.'])->assertNoContent();
    }

    /**
     * A battery of a Likert section and a drawing section, with a norm for the scale and a template that uses the scale and the signature placeholders. The
     * company may use it.
     */
    private function battery(string $template = 'Dear {{candidate_name}}: {{test_title}} - extraversion {{scale:extraversion:raw}} ({{scale:extraversion:band}}, {{scale:extraversion:standard}}). No. {{report_number}} by {{psychologist_name}} on {{signed_on}}.'): int
    {
        $id = $this->newTest('provider', ['title' => 'Personality']);

        $likert = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$likert}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();

        $drawing = (int) $this->provider('POST', "tests/{$id}/sections", ['title' => 'Draw a tree', 'format' => 'drawing', 'duration_seconds' => 900])->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$drawing}/questions", ['type' => 'drawing', 'text' => 'Draw a tree as you see it.', 'points' => 10])->assertCreated();

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 2, 'standard_score' => 40, 'band' => 'Low'], ['scale_key' => 'extraversion', 'raw_min' => 3, 'raw_max' => 10, 'standard_score' => 60, 'band' => 'High']]])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => $template])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();

        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        return $id;
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 20, 20));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** The candidate takes the battery: "Always" on the Likert statement (raw score 3) and a drawing; it is submitted. Returns the report id. */
    private function takeBattery(?int $battery = null): int
    {
        $this->invite($battery ?? $this->battery());
        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        [$likert] = $this->questionIds();
        $this->take('PUT', "attempt/questions/{$likert}/answer", ['option_positions' => [3]])->assertNoContent();
        $this->take('POST', 'attempt/next-section')->assertOk();
        $question = (int) $this->take('GET', 'attempt')->json('data.questions.0.id');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->seekerToken)->post(
            "/api/v1/me/assessment/invitations/{$this->invitationId}/attempt/questions/{$question}/drawing",
            ['file' => UploadedFile::fake()->createWithContent('drawing.png', $this->png()), 'source' => 'canvas'],
            ['Accept' => 'application/json', 'X-Attempt-Session' => (string) $this->attemptSession],
        )->assertNoContent();

        $this->take('POST', 'attempt/submit')->assertOk();

        return (int) DB::table('assessment_reports')->value('id');
    }

    private function drawingQuestionId(): int
    {
        return (int) DB::table('assessment_drawings')->value('question_id');
    }

    /** Scores the drawing and writes the explanation, so the report is ready to sign. */
    private function readyToSign(int $reportId): void
    {
        $this->provider('PUT', "reviews/{$reportId}/drawings/{$this->drawingQuestionId()}", ['score' => 7.5, 'note' => 'Roots are strong.'])->assertOk();
        $this->provider('PUT', "reviews/{$reportId}", [
            'body' => (string) DB::table('assessment_reports')->value('body'), 'explanation' => 'You like people and often seek company.', 'review_notes' => null,
        ])->assertOk();
    }

    // ----- company: results -----

    public function test_a_result_is_empty_until_the_test_is_submitted(): void
    {
        $this->inviteToWrittenTest();

        $this->asTeam('GET', 'result')->assertOk()->assertJsonPath('data.attempt', null)->assertJsonPath('data.result', null)->assertJsonPath('data.invitation.test.kind', 'skill');

        $this->take('POST', 'start', ['consent' => true])->assertCreated();

        $this->asTeam('GET', 'result')->assertOk()->assertJsonPath('data.result', null)->assertJsonPath('data.attempt.submitted_at', null);
    }

    public function test_the_result_of_a_skill_test_is_visible_to_the_members_who_see_the_application_only(): void
    {
        $this->takeWrittenTest();
        $this->take('POST', 'attempt/submit')->assertOk();

        $pending = $this->asTeam('GET', 'result')->assertOk()
            ->assertJsonPath('data.result.status', 'pending_scoring')
            ->assertJsonPath('data.result.percentage', null)
            ->assertJsonPath('data.result.band', null)
            ->assertJsonPath('data.result.integrity_tier', 'none');
        $this->assertEquals([2, 7], [$pending->json('data.result.earned_points'), $pending->json('data.result.max_points')]);

        $this->asTeam('GET', 'result', [], $this->adminToken)->assertOk();
        $this->asTeam('GET', 'result', [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "/company/assessment/invitations/{$this->invitationId}/result", [], $this->seekerToken)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken('')->getJson("/api/v1/company/assessment/invitations/{$this->invitationId}/result")->assertUnauthorized();
        $this->asTeam('GET', 'result')->assertJsonMissingPath('data.result.scales');
    }

    // ----- company: assessor scoring -----

    public function test_an_assessor_scores_the_written_answer_and_the_result_becomes_final(): void
    {
        $this->takeWrittenTest();
        $this->take('POST', 'attempt/submit')->assertOk();
        $written = (int) DB::table('assessment_questions')->where('type', 'long_text')->value('id');

        $this->asTeam('GET', 'scoring')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question_id', $written)
            ->assertJsonPath('data.0.points', 5)
            ->assertJsonPath('data.0.answer', 'I built a job portal API.')
            ->assertJsonPath('data.0.score', null);

        $this->assertEquals(4, $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 4])->assertOk()->json('data.score'));

        $scored = $this->asTeam('GET', 'result')->assertOk()->assertJsonPath('data.result.status', 'scored');
        $this->assertEquals([6, 85.71], [$scored->json('data.result.earned_points'), $scored->json('data.result.percentage')]);
        $this->assertNotNull($scored->json('data.result.band'));
        $this->assertSame($this->recruiterId, (int) DB::table('assessment_answers')->where('question_id', $written)->value('scored_by_user_id'));

        // A score can be corrected and the result follows.
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 5])->assertOk();
        $final = $this->asTeam('GET', 'result')->assertOk();
        $this->assertEquals([7, 100], [$final->json('data.result.earned_points'), $final->json('data.result.percentage')]);
    }

    public function test_scores_are_locked_once_the_application_leaves_the_assessment_stage(): void
    {
        $this->takeWrittenTest();
        $this->take('POST', 'attempt/submit')->assertOk();
        $written = (int) DB::table('assessment_questions')->where('type', 'long_text')->value('id');
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 4])->assertOk();

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertNoContent();

        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 5])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:scoring-locked');
        $this->assertEquals(4, DB::table('assessment_answers')->where('question_id', $written)->value('score'));
        $this->asTeam('GET', 'scoring')->assertOk()->assertJsonPath('data.0.score', 4);

        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'failed_screening'], $this->recruiterToken)->assertNoContent();
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 1])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:scoring-locked');
    }

    public function test_only_an_assessor_scores_and_only_a_submitted_test(): void
    {
        $this->takeWrittenTest();
        $written = (int) DB::table('assessment_questions')->where('type', 'long_text')->value('id');

        $this->asTeam('GET', 'scoring')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:attempt-not-submitted');
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 1])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:attempt-not-submitted');

        $this->take('POST', 'attempt/submit')->assertOk();

        // The company admin sees the result but was not named as an assessor.
        $this->asTeam('GET', 'scoring', [], $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-an-assessor');
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 1], $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-an-assessor');
        $this->asTeam('GET', 'scoring', [], $this->otherAdminToken)->assertNotFound();
        $this->assertNull(DB::table('assessment_answers')->where('question_id', $written)->value('score'));
    }

    public function test_a_score_must_be_a_whole_number_within_the_points_and_for_a_written_answer(): void
    {
        $this->takeWrittenTest();
        $this->take('POST', 'attempt/submit')->assertOk();
        $written = (int) DB::table('assessment_questions')->where('type', 'long_text')->value('id');
        $choice = (int) DB::table('assessment_questions')->where('type', 'single_choice')->value('id');

        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 6])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-score')->assertJsonStructure(['errors' => ['score']]);
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => 2.5])->assertStatus(422)->assertJsonValidationErrors(['score']);
        $this->asTeam('PUT', "scoring/questions/{$written}", ['score' => -1])->assertStatus(422)->assertJsonValidationErrors(['score']);
        $this->asTeam('PUT', "scoring/questions/{$written}", [])->assertStatus(422)->assertJsonValidationErrors(['score']);

        // A question that is graded by the server, or that does not exist, cannot be scored by hand.
        $this->asTeam('PUT', "scoring/questions/{$choice}", ['score' => 1])->assertNotFound();
        $this->asTeam('PUT', 'scoring/questions/999999', ['score' => 1])->assertNotFound();
        $this->asTeam('GET', 'result')->assertJsonPath('data.result.status', 'pending_scoring');
    }

    // ----- company: integrity log -----

    public function test_the_integrity_log_lists_every_signal_with_its_tier(): void
    {
        $this->takeWrittenTest();

        $this->take('POST', 'attempt/integrity-events', ['type' => 'tab_switch'])->assertNoContent();
        $this->take('POST', 'attempt/integrity-events', ['type' => 'paste'])->assertNoContent();
        $this->take('POST', 'attempt/submit')->assertOk();

        $this->asTeam('GET', 'integrity-events')->assertOk()
            ->assertJsonPath('data.tier', 'some')
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.events.0.type', 'tab_switch')
            ->assertJsonPath('data.events.1.type', 'paste')
            ->assertJsonPath('data.events.0.occurred_at', '2026-10-05T09:00:00+00:00');
        $this->asTeam('GET', 'result')->assertJsonPath('data.result.integrity_tier', 'some');

        $this->asTeam('GET', 'integrity-events', [], $this->otherAdminToken)->assertNotFound();
    }

    public function test_five_signals_are_a_major_tier_and_a_test_without_an_attempt_has_no_log(): void
    {
        $this->inviteToWrittenTest();
        $this->asTeam('GET', 'integrity-events')->assertNotFound();

        $this->take('POST', 'start', ['consent' => true])->assertCreated();

        foreach (range(1, 5) as $unused) {
            $this->take('POST', 'attempt/integrity-events', ['type' => 'fullscreen_exit'])->assertNoContent();
        }

        $this->asTeam('GET', 'integrity-events')->assertOk()->assertJsonPath('data.tier', 'major')->assertJsonPath('data.count', 5);
    }

    // ----- a psychological test: the draft -----

    public function test_submitting_a_battery_drafts_the_report_from_the_template_and_the_norms(): void
    {
        $reportId = $this->takeBattery();

        $report = (array) DB::table('assessment_reports')->first();
        $this->assertSame($reportId, (int) $report['id']);
        $this->assertSame('pending_review', $report['status']);
        $this->assertSame($this->providerId, (int) $report['provider_id']);
        $this->assertNull($report['report_number']);
        $this->assertSame(
            'Dear Siti Rahma: Personality - extraversion 3 (High, 60). No. {{report_number}} by {{psychologist_name}} on {{signed_on}}.',
            $report['body'],
        );
    }

    public function test_the_company_sees_only_that_the_report_awaits_the_psychologist(): void
    {
        $this->takeBattery();

        $response = $this->asTeam('GET', 'result')->assertOk()
            ->assertJsonPath('data.invitation.test.kind', 'psychological')
            ->assertJsonPath('data.result.status', 'awaiting_psychologist_review')
            ->assertJsonPath('data.result.integrity_tier', 'none')
            ->assertJsonPath('data.result.scales', [])
            ->assertJsonPath('data.result.report', null);

        $this->assertStringNotContainsString('extraversion', json_encode($response->json(), JSON_THROW_ON_ERROR));
        $this->asTeam('GET', 'report.pdf')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:report-not-signed');
    }

    // ----- a psychological test: the review -----

    public function test_the_review_queue_holds_only_the_reports_of_the_psychologists_provider(): void
    {
        $reportId = $this->takeBattery();

        $this->provider('GET', 'reviews')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reportId)
            ->assertJsonPath('data.0.candidate_name', 'Siti Rahma')
            ->assertJsonPath('data.0.test_title', 'Personality')
            ->assertJsonPath('data.0.status', 'pending_review')
            ->assertJsonPath('data.0.report_number', null)
            ->assertJsonPath('meta.total', 1);
        $this->provider('GET', 'reviews?filter[status]=pending_review')->assertOk()->assertJsonCount(1, 'data');
        $this->provider('GET', 'reviews?filter[status]=signed')->assertOk()->assertJsonCount(0, 'data');
        $this->provider('GET', 'reviews?filter[other]=1')->assertUnprocessable();

        // Another provider's psychologist sees nothing of it.
        $this->provider('GET', 'reviews', [], $this->otherPsychologistToken)->assertOk()->assertJsonCount(0, 'data');
        $this->provider('GET', "reviews/{$reportId}", [], $this->otherPsychologistToken)->assertNotFound();
        $this->provider('PUT', "reviews/{$reportId}", ['body' => 'Stolen', 'explanation' => null, 'review_notes' => null], $this->otherPsychologistToken)->assertNotFound();
        $this->provider('POST', "reviews/{$reportId}/sign", [], $this->otherPsychologistToken)->assertNotFound();
        $this->provider('GET', "reviews/{$reportId}/drawings/{$this->drawingQuestionId()}", [], $this->otherPsychologistToken)->assertNotFound();
    }

    public function test_the_review_endpoints_are_for_psychologists_only(): void
    {
        $reportId = $this->takeBattery();

        foreach ([$this->recruiterToken, $this->adminToken, $this->seekerToken] as $token) {
            $this->api('GET', '/provider/reviews', [], $token)->assertForbidden();
            $this->api('GET', "/provider/reviews/{$reportId}", [], $token)->assertForbidden();
            $this->api('POST', "/provider/reviews/{$reportId}/sign", [], $token)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->withToken('')->getJson('/api/v1/provider/reviews')->assertUnauthorized();

        DB::table('assessment_providers')->where('id', $this->providerId)->update(['status' => 'suspended']);
        $this->provider('GET', 'reviews')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:provider-suspended');
    }

    public function test_the_psychologist_reads_the_draft_the_scores_and_the_drawing(): void
    {
        $reportId = $this->takeBattery();

        $review = $this->provider('GET', "reviews/{$reportId}")->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.candidate_name', 'Siti Rahma')
            ->assertJsonPath('data.scales.0.scale', 'extraversion')
            ->assertJsonPath('data.scales.0.band', 'High')
            ->assertJsonPath('data.drawings.0.question_id', $this->drawingQuestionId())
            ->assertJsonPath('data.drawings.0.score', null)
            ->assertJsonPath('data.drawings.0.max_score', 10)
            ->assertJsonPath('data.speed_columns', [])
            ->assertJsonPath('data.signing_problems', ['explanation-required', 'drawings-unscored']);
        $this->assertEquals([3, 60], [$review->json('data.scales.0.raw'), $review->json('data.scales.0.standard')]);

        $drawing = $this->provider('GET', "reviews/{$reportId}/drawings/{$this->drawingQuestionId()}")->assertOk();
        $this->assertSame(DB::table('assessment_drawings')->value('mime_type'), $drawing->headers->get('Content-Type'));
        $this->assertNotSame('', (string) $drawing->getContent());
        $this->provider('GET', "reviews/{$reportId}/drawings/999999")->assertNotFound();
    }

    public function test_the_psychologist_scores_a_drawing_and_edits_the_report(): void
    {
        $reportId = $this->takeBattery();
        $question = $this->drawingQuestionId();

        $this->provider('PUT', "reviews/{$reportId}/drawings/{$question}", ['score' => 7.5, 'note' => 'Roots are strong.'])->assertOk()
            ->assertJsonPath('data.drawings.0.score', 7.5)
            ->assertJsonPath('data.drawings.0.note', 'Roots are strong.');
        $this->provider('PUT', "reviews/{$reportId}/drawings/{$question}", ['score' => 11])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-score');
        $this->provider('PUT', "reviews/{$reportId}/drawings/{$question}", ['score' => -1])->assertStatus(422);
        $this->provider('PUT', "reviews/{$reportId}/drawings/999999", ['score' => 5])->assertNotFound();
        $this->assertSame('7.50', DB::table('assessment_drawings')->value('score'));

        $this->provider('PUT', "reviews/{$reportId}", ['body' => 'My own text about {{scale:extraversion:band}}', 'explanation' => 'You like people.', 'review_notes' => 'Kraepelin looked steady.'])->assertOk()
            ->assertJsonPath('data.body', 'My own text about {{scale:extraversion:band}}')
            ->assertJsonPath('data.explanation', 'You like people.')
            ->assertJsonPath('data.review_notes', 'Kraepelin looked steady.')
            ->assertJsonPath('data.signing_problems', []);
        $this->assertNotNull(DB::table('assessment_reports')->value('edited_at'));

        $this->provider('PUT', "reviews/{$reportId}", ['body' => '   ', 'explanation' => null, 'review_notes' => null])->assertStatus(422)->assertJsonValidationErrors(['body']);
        $this->provider('PUT', "reviews/{$reportId}", ['body' => 'Text'])->assertStatus(422)->assertJsonValidationErrors(['explanation', 'review_notes']);
    }

    // ----- a psychological test: signing -----

    public function test_signing_numbers_the_report_and_fills_the_signature_placeholders(): void
    {
        $reportId = $this->takeBattery();
        $this->readyToSign($reportId);

        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk()
            ->assertJsonPath('data.status', 'signed')
            ->assertJsonPath('data.report_number', 'LEMB/2026/00001')
            ->assertJsonPath('data.signer_name', 'Psikolog Lembaga Psikologi Contoh')
            ->assertJsonPath('data.signing_problems', []);

        $report = (array) DB::table('assessment_reports')->first();
        $this->assertSame('signed', $report['status']);
        $this->assertSame([2026, 1], [(int) $report['report_year'], (int) $report['report_sequence']]);
        $this->assertSame(['STR-1', 'SIPP-1'], [$report['signer_str_number'], $report['signer_silp_number']]);
        $this->assertNotNull($report['signed_at']);
        $this->assertStringContainsString('No. LEMB/2026/00001 by Psikolog Lembaga Psikologi Contoh on 2026-10-05.', $report['body']);
        $this->assertStringNotContainsString('{{', $report['body']);

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.report_signed', 'actor_type' => 'user', 'subject_type' => 'assessment_report', 'subject_id' => $reportId]);
        $this->assertSame(['assessment.report_signed'], DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->recruiterId)->where('type', 'assessment.report_signed')->pluck('type')->all());
        $this->provider('GET', 'reviews?filter[status]=signed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.report_number', 'LEMB/2026/00001');
    }

    public function test_the_candidate_hears_that_the_explanation_is_ready_without_any_score(): void
    {
        $reportId = $this->takeBattery();
        $this->readyToSign($reportId);
        $this->assertSame([], $this->readyEmails());

        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $notification = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->seekerId)->where('type', 'assessment.explanation_ready')->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('score', strtolower((string) $notification->data));
        $this->assertStringNotContainsString('extraversion', strtolower((string) $notification->data));
        $this->assertSame([], DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'assessment.explanation_ready')->pluck('type')->all());

        $emails = $this->readyEmails();
        $this->assertCount(1, $emails);
        $this->assertSame('siti@example.test', $emails[0]->getTo()[0]->getAddress());
    }

    public function test_the_explanation_email_follows_the_application_update_preference(): void
    {
        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $reportId = $this->takeBattery();
        $this->readyToSign($reportId);

        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $this->assertSame([], $this->readyEmails());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'assessment.explanation_ready')->count());
    }

    /** @return list<Email> the emails about a ready explanation that the array mailer received so far */
    private function readyEmails(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();
        $emails = array_map(fn ($sent): Email => $sent->getOriginalMessage(), $transport->messages()->all());

        return array_values(array_filter($emails, fn (Email $email): bool => str_contains((string) $email->getSubject(), 'explanation of your test result')));
    }

    public function test_a_signed_report_is_locked(): void
    {
        $reportId = $this->takeBattery();
        $this->readyToSign($reportId);
        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $this->provider('POST', "reviews/{$reportId}/sign")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:report-signed');
        $this->provider('PUT', "reviews/{$reportId}", ['body' => 'Changed', 'explanation' => 'Changed', 'review_notes' => null])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:report-signed');
        $this->provider('PUT', "reviews/{$reportId}/drawings/{$this->drawingQuestionId()}", ['score' => 1])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:report-signed');
        $this->assertSame(1, DB::table('assessment_reports')->where('status', 'signed')->count());
    }

    public function test_a_report_cannot_be_signed_without_an_explanation_a_drawing_score_and_a_norm(): void
    {
        $reportId = $this->takeBattery();

        $this->provider('POST', "reviews/{$reportId}/sign")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:explanation-required')->assertJsonStructure(['errors' => ['explanation']]);

        $this->provider('PUT', "reviews/{$reportId}", ['body' => 'Text', 'explanation' => 'You like people.', 'review_notes' => null])->assertOk();
        $this->provider('POST', "reviews/{$reportId}/sign")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:drawings-unscored')->assertJsonStructure(['errors' => ['drawings']]);

        $this->provider('PUT', "reviews/{$reportId}/drawings/{$this->drawingQuestionId()}", ['score' => 5])->assertOk();

        // The norm that covered the raw score disappears from under the battery (an admin edit of the table, here done directly).
        DB::table('assessment_norms')->where('band', 'High')->delete();
        $this->provider('POST', "reviews/{$reportId}/sign")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:scales-not-normed')->assertJsonStructure(['errors' => ['norms']]);

        $this->assertSame(0, DB::table('assessment_reports')->where('status', 'signed')->count());
        $this->assertNull(DB::table('assessment_reports')->value('report_number'));
    }

    public function test_a_psychologist_with_an_expired_licence_cannot_sign(): void
    {
        $reportId = $this->takeBattery();
        $this->readyToSign($reportId);

        DB::table('assessment_psychologists')->update(['silp_valid_until' => '2026-10-04']);
        $this->provider('GET', "reviews/{$reportId}")->assertOk()->assertJsonPath('data.signing_problems', ['licence-invalid']);
        $this->provider('POST', "reviews/{$reportId}/sign")->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:cannot-sign-reports');

        DB::table('assessment_psychologists')->update(['silp_valid_until' => '2030-01-31', 'status' => 'suspended']);
        $this->provider('POST', "reviews/{$reportId}/sign")->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:psychologist-suspended');

        $this->assertSame(0, DB::table('assessment_reports')->where('status', 'signed')->count());
    }

    // ----- after the signature: company and candidate -----

    public function test_the_company_reads_the_signed_report_and_downloads_the_pdf(): void
    {
        $reportId = $this->takeBattery();

        $this->readyToSign($reportId);
        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $this->asTeam('GET', 'result')->assertOk()
            ->assertJsonPath('data.result.status', 'signed')
            ->assertJsonPath('data.result.scales.0.scale', 'extraversion')
            ->assertJsonPath('data.result.scales.0.band', 'High')
            ->assertJsonPath('data.result.report.number', 'LEMB/2026/00001')
            ->assertJsonPath('data.result.report.signer_name', 'Psikolog Lembaga Psikologi Contoh');

        $pdf = $this->asTeam('GET', 'report.pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('report-LEMB-2026-00001.pdf', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $this->asTeam('GET', 'report.pdf', [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "/company/assessment/invitations/{$this->invitationId}/report.pdf", [], $this->seekerToken)->assertForbidden();
    }

    public function test_the_candidate_reads_the_explanation_only_once_the_report_is_signed(): void
    {
        $reportId = $this->takeBattery();

        $this->take('GET', 'explanation')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:report-not-signed');

        $this->readyToSign($reportId);
        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $response = $this->take('GET', 'explanation')->assertOk()
            ->assertJsonPath('data.report_number', 'LEMB/2026/00001')
            ->assertJsonPath('data.test_title', 'Personality')
            ->assertJsonPath('data.signer_name', 'Psikolog Lembaga Psikologi Contoh')
            ->assertJsonPath('data.explanation', 'You like people and often seek company.')
            ->assertJsonPath('data.scales.0.scale', 'extraversion');
        $this->assertEquals(60, $response->json('data.scales.0.standard'));

        // The company's full report text is not handed to the candidate.
        $this->assertStringNotContainsString('Dear Siti Rahma', json_encode($response->json(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Roots are strong', json_encode($response->json(), JSON_THROW_ON_ERROR));
    }

    public function test_the_explanation_belongs_to_the_candidate_who_took_the_test(): void
    {
        $this->takeBattery();

        $this->api('GET', "/me/assessment/invitations/{$this->invitationId}/explanation", [], $this->otherAdminToken)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken('')->getJson("/api/v1/me/assessment/invitations/{$this->invitationId}/explanation")->assertUnauthorized();
        $this->take('GET', 'explanation')->assertStatus(409);
        $this->api('GET', '/me/assessment/invitations/999999/explanation', [], $this->seekerToken)->assertNotFound();
    }

    public function test_a_skill_test_has_no_explanation(): void
    {
        $this->takeWrittenTest();
        $this->take('POST', 'attempt/submit')->assertOk();

        $this->take('GET', 'explanation')->assertNotFound();
    }

    // ----- erasure -----

    public function test_the_anonymisation_erases_the_answers_the_drawings_and_the_text_of_the_report(): void
    {
        $reportId = $this->takeBattery();
        $this->readyToSign($reportId);
        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();
        $this->take('GET', 'attempt');

        $paths = DB::table('assessment_drawings')->pluck('path')->all();
        $this->assertCount(1, $paths);
        Storage::assertExists($paths[0]);
        DB::table('assessment_integrity_events')->insert(['attempt_id' => DB::table('assessment_attempts')->value('id'), 'type' => 'paste', 'occurred_at' => now()]);

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        Storage::assertMissing($paths[0]);
        $this->assertSame(0, DB::table('assessment_answers')->count());
        $this->assertSame(0, DB::table('assessment_drawings')->count());
        $this->assertSame(0, DB::table('assessment_integrity_events')->count());
        $this->assertNull(DB::table('assessment_attempts')->value('last_ip'));
        $this->assertNull(DB::table('assessment_results')->value('scale_scores'));

        // The provider's register keeps the number and the signature; the text and the explanation are gone.
        $report = (array) DB::table('assessment_reports')->first();
        $this->assertSame(['signed', 'LEMB/2026/00001', ''], [$report['status'], $report['report_number'], $report['body']]);
        $this->assertNull($report['explanation']);
        $this->assertNull($report['review_notes']);
        $this->assertSame(1, DB::table('assessment_invitations')->count());
        $this->assertSame(1, DB::table('identity_erasure_receipts')->where('user_id', $this->seekerId)->where('step', 'assessment-data')->count());

        // Running it again changes nothing.
        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));
        $this->assertSame('', DB::table('assessment_reports')->value('body'));
    }

    // ----- units -----

    public function test_a_norm_covers_the_rounded_raw_score_of_its_scale_only(): void
    {
        $norms = [new Norm('a', 0, 2, 40.0, 'Low'), new Norm('a', 3, 10, 60.0, 'High'), new Norm('b', 0, 10, 50.0, 'Mid')];

        $this->assertSame('Low', NormConverter::convert($norms, 'a', 2.4)?->band);
        $this->assertSame('High', NormConverter::convert($norms, 'a', 2.5)?->band);
        $this->assertSame('Mid', NormConverter::convert($norms, 'b', 7.0)?->band);
        $this->assertNull(NormConverter::convert($norms, 'a', 11.0));
        $this->assertNull(NormConverter::convert($norms, 'c', 1.0));
    }

    public function test_the_renderer_fills_the_draft_then_the_signature_and_leaves_unknown_placeholders(): void
    {
        $draft = ReportRenderer::draft(
            '{{candidate_name}} {{test_title}} {{scale:a:raw}} {{scale:a:standard}} {{scale:a:band}} {{scale:b:band}} {{report_number}}',
            'Sari',
            'Test',
            ['a' => 12.5, 'b' => 99.0],
            [new Norm('a', 12, 13, 55.0, 'Mid')],
        );

        $this->assertSame('Sari Test 12.5 55 Mid n/a {{report_number}}', $draft);
        $this->assertSame('x A/2026/00001 y 2026-10-05 z Dr. Ayu', ReportRenderer::signed('x {{report_number}} y {{signed_on}} z {{psychologist_name}}', 'A/2026/00001', '2026-10-05', 'Dr. Ayu'));
    }
}
