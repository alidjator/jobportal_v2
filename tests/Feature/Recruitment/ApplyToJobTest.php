<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\ScreeningQuestionUsage;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\StateConflict;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ApplyToJobTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- a successful application -----

    public function test_a_job_seeker_applies_and_the_application_is_stored_whole(): void
    {
        $job = $this->liveJob();

        $response = $this->apply($job, ['cover_letter' => '  I would love to join.  '])->assertCreated();
        $response->assertJsonPath('data.job_id', $job)->assertJsonPath('data.status', 'applied')->assertJsonMissingPath('data.meets_must_have');

        $row = DB::table('recruitment_applications')->where('id', $response->json('data.id'))->first();
        $appliedStage = DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'applied')->where('is_default', 1)->value('id');

        $this->assertSame($this->seekerId, (int) $row->user_id);
        $this->assertSame($job, (int) $row->job_id);
        $this->assertSame($this->companyId, (int) $row->company_id);
        $this->assertSame((int) $appliedStage, (int) $row->stage_id);
        $this->assertSame('applied', $row->stage_type);
        $this->assertSame($this->seekerId.'-'.$job, $row->active_key);
        $this->assertSame('I would love to join.', $row->cover_letter);
        $this->assertNull($row->meets_must_have);
        $this->assertSame('2026-10-05 09:00:00', $row->applied_at);

        $history = DB::table('recruitment_application_stage_history')->where('application_id', $row->id)->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->from_type);
        $this->assertSame('applied', $history[0]->to_type);
        $this->assertSame('applicant', $history[0]->actor_kind);
        $this->assertSame($this->seekerId, (int) $history[0]->actor_id);
    }

    public function test_the_application_keeps_its_own_copy_of_the_resume(): void
    {
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        $row = DB::table('recruitment_applications')->first();
        $original = DB::table('jobseeker_resumes')->where('id', $this->resumeId)->value('path');

        $this->assertNotSame($original, $row->resume_path);
        $this->assertStringStartsWith('recruitment/applications/', $row->resume_path);
        $this->assertSame('Siti CV.pdf', $row->resume_name);
        $this->assertSame(strlen($this->pdf()), (int) $row->resume_size_bytes);
        Storage::assertExists($row->resume_path);
        $this->assertSame($this->pdf(), Storage::get($row->resume_path));

        // The job seeker may delete the resume afterwards: the application still has its file.
        $this->api('DELETE', "/me/resumes/{$this->resumeId}", token: $this->seekerToken)->assertNoContent();
        Storage::assertMissing($original);
        Storage::assertExists($row->resume_path);
    }

    public function test_the_profile_snapshot_is_frozen_and_leaves_out_the_sensitive_fields(): void
    {
        $this->apply($this->liveJob())->assertCreated();
        $snapshot = json_decode((string) DB::table('recruitment_applications')->value('profile_snapshot'), true);

        $this->assertSame('Siti Rahma', $snapshot['name']);
        $this->assertSame('siti@example.test', $snapshot['email']);
        $this->assertSame('081234567890', $snapshot['phone']);
        $this->assertSame('Backend developer', $snapshot['headline']);
        $this->assertSame(28, $snapshot['age']);
        $this->assertSame('BANDUNG', $snapshot['location']['city']);
        $this->assertSame('Backend Developer', $snapshot['experiences'][0]['job_title']);
        $this->assertSame('2022-03', $snapshot['experiences'][0]['start']);

        foreach (['gender', 'religion', 'marital_status', 'nationality', 'birth_date'] as $field) {
            $this->assertArrayNotHasKey($field, $snapshot);
        }

        // Changing the profile afterwards does not change what was submitted.
        $this->api('PATCH', '/me/profile', ['headline' => 'Changed'], $this->seekerToken)->assertOk();
        $this->assertSame('Backend developer', json_decode((string) DB::table('recruitment_applications')->value('profile_snapshot'), true)['headline']);
    }

    public function test_the_slug_form_of_the_job_id_works(): void
    {
        $job = $this->liveJob();

        $this->apply("{$job}-backend-developer")->assertCreated();
    }

    public function test_the_assigned_team_members_are_notified_in_app(): void
    {
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);

        $id = (int) $this->apply($job)->assertCreated()->json('data.id');

        foreach ([$this->recruiterId, $this->secondRecruiterId] as $userId) {
            $notification = DB::table('notifications')->where('notifiable_id', $userId)->where('type', 'application.received')->first();
            $this->assertNotNull($notification);
            $this->assertSame(['application_id' => $id, 'job_id' => $job], json_decode($notification->data, true));
        }

        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->adminId)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->seekerId)->count());
    }

    // ----- who may apply -----

    public function test_an_application_needs_a_signed_in_job_seeker_with_a_verified_email(): void
    {
        $job = $this->liveJob();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson("/api/v1/jobs/{$job}/applications", ['resume_id' => 1])->assertStatus(401);

        $this->apply($job, token: $this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');

        $unverified = $this->tokenFor($this->seeker('new@example.test', null));
        $this->apply($job, token: $unverified)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:email-not-verified');
        $this->assertSame(0, DB::table('recruitment_applications')->count());
    }

    public function test_only_a_live_public_job_takes_applications(): void
    {
        foreach (['draft', 'pending_review', 'closed'] as $status) {
            $this->apply($this->insertJob('Job '.$status, $status, attributes: ['deadline' => '2026-12-31']))->assertStatus(404);
        }

        $this->apply($this->insertJob('Late job', 'active', attributes: ['deadline' => '2026-10-01']))->assertStatus(404);
        $this->apply(999999)->assertStatus(404);
        $this->assertSame(0, DB::table('recruitment_applications')->count());
    }

    public function test_applying_twice_to_the_same_job_is_refused_and_leaves_no_extra_file(): void
    {
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        $this->apply($job)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-applied');

        $this->assertSame(1, DB::table('recruitment_applications')->count());
        $this->assertCount(1, Storage::allFiles('recruitment/applications'));
    }

    public function test_the_unique_key_stops_a_duplicate_that_slips_past_the_check(): void
    {
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        // Simulates a lost race: the check saw nothing, but the insert meets the unique key.
        $repository = $this->app->make(ApplicationRepository::class);
        $existing = DB::table('recruitment_applications')->first();

        $this->expectException(StateConflict::class);

        $repository->add(new Application(
            $this->seekerId, $job, $this->companyId, (int) $existing->stage_id, StageType::Applied, null, null, [], null, null, new \DateTimeImmutable('2026-10-05 09:00:00'),
        ), []);
    }

    // ----- the profile and the resume -----

    public function test_an_incomplete_profile_cannot_apply_and_the_answer_lists_what_is_missing(): void
    {
        $token = $this->tokenFor($this->seeker('empty@example.test'));

        $this->apply($this->liveJob(), ['resume_id' => 1], $token)->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:profile-incomplete')
            ->assertJsonPath('missing', ['phone', 'location', 'history', 'resume']);
    }

    public function test_a_resume_of_another_account_or_a_missing_one_is_refused(): void
    {
        $other = $this->tokenFor($this->seeker('other@example.test'));
        $otherResume = $this->completeProfile($other);
        $job = $this->liveJob();

        $this->apply($job, ['resume_id' => $otherResume])->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-application')->assertJsonPath('errors.resume_id.0', 'Choose one of your own resumes.');
        $this->apply($job, ['resume_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-application');
        $this->assertSame(0, DB::table('recruitment_applications')->count());
    }

    public function test_the_cover_letter_is_optional_and_limited(): void
    {
        $job = $this->liveJob();

        $this->apply($job, ['cover_letter' => str_repeat('a', 2001)])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-application')->assertJsonStructure(['errors' => ['cover_letter']]);
        $this->apply($job, ['cover_letter' => '   '])->assertCreated();

        $this->assertNull(DB::table('recruitment_applications')->value('cover_letter'));
    }

    public function test_the_request_shape_is_checked(): void
    {
        $job = $this->liveJob();

        $this->api('POST', "/jobs/{$job}/applications", [], $this->seekerToken)->assertStatus(422);
        $this->apply($job, ['answers' => [['question_id' => 1]]])->assertStatus(422);
        $this->apply($job, ['answers' => [['question_id' => 1, 'value' => true], ['question_id' => 1, 'value' => false]]])->assertStatus(422);
    }

    // ----- the daily limit -----

    public function test_the_daily_limit_counts_every_application_of_the_day(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(RecruitmentSettings::MAX_APPLICATIONS_PER_DAY, 2, null);
        $first = $this->liveJob();
        $second = $this->insertJob('Second job', 'active', attributes: ['deadline' => '2026-12-31']);
        $third = $this->insertJob('Third job', 'active', attributes: ['deadline' => '2026-12-31']);

        $this->apply($first)->assertCreated();
        $this->apply($second)->assertCreated();

        $response = $this->apply($third)->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:application-limit-reached');
        // 09:00 UTC is 16:00 in Jakarta: eight hours to midnight.
        $this->assertSame('28800', $response->headers->get('Retry-After'));

        // The next Jakarta day starts over.
        Carbon::setTestNow('2026-10-06 00:00:00');
        $this->apply($third)->assertCreated();
    }

    // ----- screening answers -----

    public function test_required_questions_need_an_answer_and_nothing_is_stored_when_one_is_wrong(): void
    {
        $job = $this->liveJob();
        $relocate = $this->question('yes_no', 'Can you relocate?');
        $city = $this->question('single_choice', 'Where do you live?', ['Jakarta', 'Bandung']);
        $this->setQuestions($job, [['question_id' => $relocate, 'required' => true], ['question_id' => $city]]);

        $errors = fn (TestResponse $response): array => $response->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-answers')->json('errors');

        $this->assertSame(['This question needs an answer.'], $errors($this->apply($job))['answers.'.$relocate]);
        $this->assertSame(['The answer does not fit the question.'], $errors($this->apply($job, ['answers' => [['question_id' => $relocate, 'value' => 'yes']]]))['answers.'.$relocate]);
        $this->assertArrayHasKey('answers.'.$city, $errors($this->apply($job, ['answers' => [['question_id' => $relocate, 'value' => true], ['question_id' => $city, 'value' => 999999]]])));
        $this->assertSame(['This question does not belong to the job.'], $errors($this->apply($job, ['answers' => [['question_id' => $relocate, 'value' => true], ['question_id' => 424242, 'value' => true]]]))['answers.424242']);

        $this->assertSame(0, DB::table('recruitment_applications')->count());
        $this->assertSame(0, DB::table('recruitment_application_answers')->count());
        $this->assertCount(0, Storage::allFiles('recruitment/applications'));
    }

    public function test_every_answer_type_is_stored_in_its_own_column(): void
    {
        $job = $this->liveJob();
        $text = $this->question('text', 'Why this job?');
        $yesNo = $this->question('yes_no', 'Can you relocate?');
        $number = $this->question('number', 'Years of experience?');
        $single = $this->question('single_choice', 'Where do you live?', ['Jakarta', 'Bandung']);
        $multiple = $this->question('multiple_choice', 'Which tools?', ['Git', 'Docker', 'Vim']);
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id, 'required' => true], [$text, $yesNo, $number, $single, $multiple]));

        $bandung = $this->optionId($single, 'Bandung');
        $git = $this->optionId($multiple, 'Git');
        $docker = $this->optionId($multiple, 'Docker');

        $id = (int) $this->apply($job, ['answers' => [
            ['question_id' => $text, 'value' => '  Because I like it.  '],
            ['question_id' => $yesNo, 'value' => false],
            ['question_id' => $number, 'value' => 4],
            ['question_id' => $single, 'value' => $bandung],
            ['question_id' => $multiple, 'value' => [$git, $docker]],
        ]])->assertCreated()->json('data.id');

        $answers = DB::table('recruitment_application_answers')->where('application_id', $id)->get()->keyBy('question_id');
        $this->assertSame('Because I like it.', $answers[$text]->answer_text);
        $this->assertSame(0, (int) $answers[$yesNo]->answer_bool);
        $this->assertNotNull($answers[$yesNo]->answer_bool);
        $this->assertSame(4, (int) $answers[$number]->answer_number);

        $chosen = fn (int $question): array => array_map('intval', DB::table('recruitment_application_answer_options')->where('answer_id', $answers[$question]->id)->orderBy('option_id')->pluck('option_id')->all());
        $this->assertSame([$bandung], $chosen($single));
        $this->assertSame([$git, $docker], $chosen($multiple));
    }

    public function test_an_optional_question_may_stay_unanswered(): void
    {
        $job = $this->liveJob();
        $this->setQuestions($job, [['question_id' => $this->question('text', 'Anything to add?')]]);

        $this->apply($job)->assertCreated();

        $this->assertSame(0, DB::table('recruitment_application_answers')->count());
    }

    public function test_a_must_have_is_only_a_mark_and_the_application_still_arrives(): void
    {
        $job = $this->liveJob();
        $relocate = $this->question('yes_no', 'Can you relocate?');
        $years = $this->question('number', 'Years of experience?');
        $this->setQuestions($job, [
            ['question_id' => $relocate, 'must_have' => true, 'ideal' => ['yes' => true]],
            ['question_id' => $years, 'must_have' => true, 'ideal' => ['min' => 3]],
        ]);

        $met = (int) $this->apply($job, ['answers' => [['question_id' => $relocate, 'value' => true], ['question_id' => $years, 'value' => 5]]])->assertCreated()->json('data.id');
        $this->assertSame(1, (int) DB::table('recruitment_applications')->where('id', $met)->value('meets_must_have'));

        $secondJob = $this->insertJob('Second job', 'active', attributes: ['deadline' => '2026-12-31']);
        $this->setQuestions($secondJob, [['question_id' => $relocate, 'must_have' => true, 'ideal' => ['yes' => true]]]);
        $failed = (int) $this->apply($secondJob, ['answers' => [['question_id' => $relocate, 'value' => false]]])->assertCreated()->json('data.id');
        $this->assertSame(0, (int) DB::table('recruitment_applications')->where('id', $failed)->value('meets_must_have'));
    }

    public function test_screening_switched_off_for_the_company_means_no_questions_are_asked(): void
    {
        $job = $this->liveJob();
        $this->setQuestions($job, [['question_id' => $this->question('yes_no', 'Can you relocate?'), 'required' => true]]);
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, false, null);

        $this->apply($job)->assertCreated();
    }

    // ----- what applications lock -----

    public function test_a_job_and_its_questions_are_locked_once_an_application_exists(): void
    {
        $job = $this->liveJob();
        $question = $this->question('yes_no', 'Can you relocate?');
        $this->setQuestions($job, [['question_id' => $question]]);
        $other = $this->insertJob('Other job', 'active', attributes: ['deadline' => '2026-12-31']);

        $this->assertFalse($this->app->make(JobApplicationCheck::class)->hasApplications($job));
        $this->assertFalse($this->app->make(ScreeningQuestionUsage::class)->isLocked($question));

        $this->apply($job)->assertCreated();

        $this->assertTrue($this->app->make(JobApplicationCheck::class)->hasApplications($job));
        $this->assertFalse($this->app->make(JobApplicationCheck::class)->hasApplications($other));
        $this->assertTrue($this->app->make(ScreeningQuestionUsage::class)->isLocked($question));
        $this->api('PUT', "/company/jobs/{$job}/screening-questions", ['questions' => []])->assertStatus(409);
    }

    public function test_an_answered_question_is_locked_and_cannot_be_deleted(): void
    {
        $job = $this->liveJob();
        $question = $this->question('yes_no', 'Can you relocate?');
        $this->setQuestions($job, [['question_id' => $question]]);
        $this->apply($job, ['answers' => [['question_id' => $question, 'value' => true]]])->assertCreated();

        $this->assertTrue($this->app->make(ScreeningQuestionUsage::class)->isLocked($question));
        $this->api('DELETE', "/company/screening-questions/{$question}")->assertStatus(409);
    }
}
