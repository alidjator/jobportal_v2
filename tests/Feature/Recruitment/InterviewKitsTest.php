<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-7-interview-kits.md`, part 1: the kit library, the job defaults, the panel and the copy of the questions an interview keeps. */
final class InterviewKitsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function kitBody(string $name = 'Backend round', ?array $questions = null): array
    {
        return ['name' => $name, 'questions' => $questions ?? [
            ['text' => 'Explain a database transaction.', 'guidance' => 'Look for isolation levels.', 'weight' => 5],
            ['text' => 'Tell us about a hard bug.', 'weight' => 3],
        ]];
    }

    private function createKit(string $name = 'Backend round', ?array $questions = null, ?string $token = null): int
    {
        return (int) $this->api('POST', '/company/interview-kits', $this->kitBody($name, $questions), $token ?? $this->recruiterToken)->assertCreated()->json('data.id');
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** Moves the application to the interview stage with the schedule, or schedules again when it is there already. */
    private function schedule(array $extra = [], ?string $token = null): TestResponse
    {
        $schedule = $this->interviewSchedule($extra);
        $token ??= $this->recruiterToken;

        if (DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type') === 'interview') {
            return $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $schedule, $token);
        }

        return $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => $schedule], $token);
    }

    private function activeInterviewId(): int
    {
        return (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->whereNotNull('active_key')->value('id');
    }

    /** @return list<string> the question texts an interview keeps */
    private function copiedTexts(int $interviewId): array
    {
        return DB::table('recruitment_interview_questions')->where('interview_id', $interviewId)->orderBy('position')->pluck('text')->all();
    }

    // ----- the library -----

    public function test_a_member_creates_reads_replaces_and_deletes_a_kit(): void
    {
        $id = $this->createKit();

        $this->api('GET', "/company/interview-kits/{$id}", [], $this->adminToken)->assertOk()
            ->assertJsonPath('data.name', 'Backend round')
            ->assertJsonPath('data.questions.0.position', 1)
            ->assertJsonPath('data.questions.0.guidance', 'Look for isolation levels.')
            ->assertJsonPath('data.questions.1.guidance', null)
            ->assertJsonPath('data.questions.1.weight', 3)
            ->assertJsonPath('data.created_by', $this->recruiterId);

        $this->api('PUT', "/company/interview-kits/{$id}", $this->kitBody('Backend round v2', [['text' => 'Only one now.', 'weight' => 1]]), $this->adminToken)->assertOk()
            ->assertJsonPath('data.name', 'Backend round v2')
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonPath('data.created_by', $this->recruiterId)
            ->assertJsonPath('data.updated_by', $this->adminId);

        $this->api('DELETE', "/company/interview-kits/{$id}", [], $this->recruiterToken)->assertNoContent();
        $this->assertSame(0, DB::table('recruitment_interview_kit_questions')->count());
        $this->api('GET', "/company/interview-kits/{$id}")->assertNotFound();
    }

    public function test_the_list_shows_the_kits_of_the_own_company_by_name_without_their_questions(): void
    {
        $this->createKit('Zeta');
        $this->createKit('alpha');
        $this->createKit('Beta', null, $this->otherAdminToken);

        $response = $this->api('GET', '/company/interview-kits', [], $this->adminToken)->assertOk();

        $this->assertSame(['alpha', 'Zeta'], array_column($response->json('data'), 'name'));
        $response->assertJsonPath('data.0.questions_count', 2)->assertJsonMissingPath('data.0.questions');
    }

    public function test_a_kit_of_another_company_does_not_exist_for_the_caller(): void
    {
        $foreign = $this->createKit('Foreign', null, $this->otherAdminToken);

        $this->api('GET', "/company/interview-kits/{$foreign}")->assertNotFound();
        $this->api('PUT', "/company/interview-kits/{$foreign}", $this->kitBody())->assertNotFound();
        $this->api('DELETE', "/company/interview-kits/{$foreign}")->assertNotFound();
        $this->assertSame(1, DB::table('recruitment_interview_kits')->count());
    }

    public function test_only_company_members_use_the_library(): void
    {
        $this->api('GET', '/company/interview-kits', [], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->api('POST', '/company/interview-kits', $this->kitBody(), $this->seekerToken)->assertForbidden();
    }

    public function test_a_name_is_unique_per_company_ignoring_case(): void
    {
        $id = $this->createKit('Backend round');

        $this->api('POST', '/company/interview-kits', $this->kitBody('BACKEND ROUND'))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:kit-name-taken');
        $this->createKit('Backend round', null, $this->otherAdminToken);

        $other = $this->createKit('Other');
        $this->api('PUT', "/company/interview-kits/{$other}", $this->kitBody('backend Round'))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:kit-name-taken');
        $this->api('PUT', "/company/interview-kits/{$id}", $this->kitBody('BACKEND round'))->assertOk();
    }

    public function test_a_kit_that_breaks_a_rule_is_refused_with_the_field(): void
    {
        $this->api('POST', '/company/interview-kits', $this->kitBody('Kit', [['text' => str_repeat('q', 501), 'weight' => 9]]))
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-kit')
            ->assertJsonStructure(['errors' => ['questions.0.text', 'questions.0.weight']]);

        $this->api('POST', '/company/interview-kits', $this->kitBody('Kit', []))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->api('POST', '/company/interview-kits', $this->kitBody('Kit', array_fill(0, 21, ['text' => 'Q', 'weight' => 1])))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-kit');
        $this->api('POST', '/company/interview-kits', $this->kitBody(str_repeat('n', 101)))->assertStatus(422)->assertJsonStructure(['errors' => ['name']]);
        $this->assertSame(0, DB::table('recruitment_interview_kits')->count());
    }

    public function test_a_company_cannot_pass_its_kit_limit(): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_INTERVIEW_KITS, $this->companyId, 2, null);

        $this->createKit('One');
        $this->createKit('Two');

        $this->api('POST', '/company/interview-kits', $this->kitBody('Three'))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:kit-limit-reached');
        $this->createKit('Three', null, $this->otherAdminToken);
    }

    // ----- the default kit of a job -----

    public function test_a_job_keeps_a_default_kit_per_interview_stage(): void
    {
        $kit = $this->createKit();
        $stage = $this->stage('interview');

        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$stage}", ['kit_id' => $kit])->assertOk()
            ->assertJsonPath('data.stage.id', $stage)
            ->assertJsonPath('data.kit.name', 'Backend round');

        $other = $this->createKit('Second');
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$stage}", ['kit_id' => $other])->assertOk();

        $this->api('GET', "/company/jobs/{$this->job}/interview-kits")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kit.id', $other);

        $this->api('DELETE', "/company/jobs/{$this->job}/interview-kits/{$stage}")->assertNoContent();
        $this->api('GET', "/company/jobs/{$this->job}/interview-kits")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_default_kit_needs_an_interview_stage_of_the_company_and_a_kit_of_the_company(): void
    {
        $kit = $this->createKit();

        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('offer')}", ['kit_id' => $kit])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:stage-not-interview');
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/987654", ['kit_id' => $kit])->assertNotFound();
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('interview')}", ['kit_id' => $this->createKit('Foreign', null, $this->otherAdminToken)])->assertNotFound();
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('interview')}", [])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
    }

    public function test_a_member_who_cannot_see_the_job_cannot_set_its_default_kit(): void
    {
        $unassigned = $this->insertJob('Hidden Job', 'active', [$this->adminId]);

        $this->api('GET', "/company/jobs/{$unassigned}/interview-kits", [], $this->recruiterToken)->assertNotFound();
        $this->api('PUT', "/company/jobs/{$unassigned}/interview-kits/{$this->stage('interview')}", ['kit_id' => $this->createKit()], $this->recruiterToken)->assertNotFound();
    }

    public function test_deleting_a_kit_removes_the_defaults_that_named_it(): void
    {
        $kit = $this->createKit();
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('interview')}", ['kit_id' => $kit])->assertOk();

        $this->api('DELETE', "/company/interview-kits/{$kit}")->assertNoContent();

        $this->api('GET', "/company/jobs/{$this->job}/interview-kits")->assertJsonCount(0, 'data');
    }

    // ----- scheduling with a kit -----

    public function test_an_interview_keeps_a_copy_of_the_kit_it_was_scheduled_with(): void
    {
        $kit = $this->createKit();

        $this->schedule(['kit_id' => $kit])->assertNoContent();
        $interview = $this->activeInterviewId();

        $this->api('GET', "/company/interviews/{$interview}/questions")->assertOk()
            ->assertJsonPath('data.kit.name', 'Backend round')
            ->assertJsonPath('data.locked', false)
            ->assertJsonPath('data.questions.0.guidance', 'Look for isolation levels.')
            ->assertJsonPath('data.questions.1.text', 'Tell us about a hard bug.');

        $this->api('PUT', "/company/interview-kits/{$kit}", $this->kitBody('Renamed', [['text' => 'Something else.', 'weight' => 2]]))->assertOk();
        $this->assertSame(['Explain a database transaction.', 'Tell us about a hard bug.'], $this->copiedTexts($interview));

        $this->api('DELETE', "/company/interview-kits/{$kit}")->assertNoContent();
        $this->assertCount(2, $this->copiedTexts($interview));
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews")->assertOk()
            ->assertJsonPath('data.0.kit.id', null)
            ->assertJsonPath('data.0.kit.name', 'Backend round');
    }

    public function test_the_default_kit_of_the_job_is_used_when_the_request_names_none(): void
    {
        $kit = $this->createKit();
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('interview')}", ['kit_id' => $kit])->assertOk();

        $this->schedule()->assertNoContent();

        $this->assertCount(2, $this->copiedTexts($this->activeInterviewId()));
        $this->assertSame($kit, (int) DB::table('recruitment_interviews')->where('id', $this->activeInterviewId())->value('kit_id'));
    }

    public function test_a_null_kit_means_no_kit_even_when_the_job_has_a_default(): void
    {
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('interview')}", ['kit_id' => $this->createKit()])->assertOk();

        $this->schedule(['kit_id' => null])->assertNoContent();

        $this->assertSame([], $this->copiedTexts($this->activeInterviewId()));
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews")->assertJsonPath('data.0.kit', null);
    }

    public function test_a_kit_of_another_company_cannot_be_used_to_schedule(): void
    {
        $foreign = $this->createKit('Foreign', null, $this->otherAdminToken);

        $this->schedule(['kit_id' => $foreign])->assertNotFound();

        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));
    }

    public function test_a_new_time_keeps_the_edited_questions_and_the_panel_unless_the_request_changes_them(): void
    {
        $this->schedule(['kit_id' => $this->createKit(), 'participant_ids' => [$this->adminId]])->assertNoContent();
        $first = $this->activeInterviewId();
        $this->api('PUT', "/company/interviews/{$first}/questions", ['questions' => [['text' => 'Edited question.', 'weight' => 4]]])->assertOk();

        $this->schedule(['starts_at' => now()->addDays(5)->utc()->format('Y-m-d\TH:i:s\Z')])->assertOk();
        $second = $this->activeInterviewId();

        $this->assertNotSame($first, $second);
        $this->assertSame(['Edited question.'], $this->copiedTexts($second));
        $this->assertSame([$this->adminId], DB::table('recruitment_interview_participants')->where('interview_id', $second)->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('Backend round', DB::table('recruitment_interviews')->where('id', $second)->value('kit_name'));
        $this->assertSame(['Edited question.'], $this->copiedTexts($first), 'The replaced interview keeps its own copy.');

        $this->schedule(['participant_ids' => []])->assertOk();
        $this->assertSame(0, DB::table('recruitment_interview_participants')->where('interview_id', $this->activeInterviewId())->count());

        $fresh = $this->createKit('Fresh', [['text' => 'Brand new.', 'weight' => 1]]);
        $this->schedule(['kit_id' => $fresh])->assertOk();
        $this->assertSame(['Brand new.'], $this->copiedTexts($this->activeInterviewId()));
    }

    // ----- the panel -----

    public function test_the_panel_is_stored_and_shown_to_the_team_only(): void
    {
        $this->schedule(['participant_ids' => [$this->adminId, $this->secondRecruiterId]])->assertNoContent();

        $this->api('GET', "/company/applications/{$this->applicationId}/interviews")->assertOk()
            ->assertJsonPath('data.0.participants.0.id', $this->adminId)
            ->assertJsonPath('data.0.participants.0.name', 'Andi Admin')
            ->assertJsonPath('data.0.participants.1.id', $this->secondRecruiterId);

        $mine = $this->api('GET', "/me/applications/{$this->applicationId}/interview", [], $this->seekerToken)->assertOk();
        $mine->assertJsonMissingPath('data.participants')->assertJsonMissingPath('data.kit');
    }

    public function test_the_panel_is_checked(): void
    {
        $this->schedule(['participant_ids' => [$this->recruiterId]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->schedule(['participant_ids' => [$this->otherAdminId]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->schedule(['participant_ids' => [$this->adminId, $this->adminId]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->schedule(['participant_ids' => [1, 2, 3, 4]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');

        $this->assertSame(0, DB::table('recruitment_interviews')->count());
    }

    public function test_a_member_who_does_not_work_on_the_job_cannot_join_the_panel(): void
    {
        DB::table('job_assignments')->where('job_id', $this->job)->where('user_id', $this->secondRecruiterId)->delete();

        $this->schedule(['participant_ids' => [$this->secondRecruiterId]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
    }

    // ----- editing the copy -----

    public function test_the_scheduler_the_interviewer_and_an_admin_edit_the_copy_and_other_members_cannot(): void
    {
        $this->schedule(['kit_id' => $this->createKit(), 'interviewer_id' => $this->adminId])->assertNoContent();
        $interview = $this->activeInterviewId();
        $body = ['questions' => [['text' => 'Edited.', 'guidance' => 'Be kind.', 'weight' => 2]]];

        $this->api('PUT', "/company/interviews/{$interview}/questions", $body, $this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:interview-edit-denied');
        $this->api('GET', "/company/interviews/{$interview}/questions", [], $this->secondRecruiterToken)->assertOk();

        $this->api('PUT', "/company/interviews/{$interview}/questions", $body, $this->recruiterToken)->assertOk();
        $this->api('PUT', "/company/interviews/{$interview}/questions", ['questions' => []], $this->adminToken)->assertOk()->assertJsonPath('data.questions', []);
        $this->assertSame([], $this->copiedTexts($interview));
    }

    public function test_the_copy_is_checked_like_a_kit_but_may_be_empty(): void
    {
        $this->schedule()->assertNoContent();
        $interview = $this->activeInterviewId();

        $this->api('PUT', "/company/interviews/{$interview}/questions", ['questions' => [['text' => '', 'weight' => 3]]])->assertStatus(422);
        $this->api('PUT', "/company/interviews/{$interview}/questions", ['questions' => [['text' => 'Q', 'weight' => 6]]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-kit');
        $this->api('PUT', "/company/interviews/{$interview}/questions", [])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->api('PUT', "/company/interviews/{$interview}/questions", ['questions' => []])->assertOk();
    }

    public function test_an_interview_of_another_company_does_not_exist_for_the_caller(): void
    {
        $this->schedule()->assertNoContent();
        $interview = $this->activeInterviewId();

        $this->api('GET', "/company/interviews/{$interview}/questions", [], $this->otherAdminToken)->assertNotFound();
        $this->api('PUT', "/company/interviews/{$interview}/questions", ['questions' => []], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/interviews/987654/questions')->assertNotFound();
    }
}
