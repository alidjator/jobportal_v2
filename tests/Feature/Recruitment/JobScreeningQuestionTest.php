<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class JobScreeningQuestionTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @param  list<string>  $labels */
    private function question(string $type = 'yes_no', string $text = 'Can you start next month?', array $labels = [], ?string $token = null): int
    {
        $body = ['type' => $type, 'text' => $text];

        if ($labels !== []) {
            $body['options'] = array_map(fn (string $label): array => ['label' => $label], $labels);
        }

        return (int) $this->api('POST', '/company/screening-questions', $body, $token)->assertCreated()->json('data.id');
    }

    private function optionId(int $questionId, string $label): int
    {
        return (int) DB::table('recruitment_screening_question_options')->where('question_id', $questionId)->where('label', $label)->value('id');
    }

    /** @param  list<array<string, mixed>>  $questions */
    private function setQuestions(int $jobId, array $questions, ?string $token = null): TestResponse
    {
        return $this->api('PUT', "/company/jobs/{$jobId}/screening-questions", ['questions' => $questions], $token);
    }

    private function publicQuestions(string $job): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson("/api/v1/jobs/{$job}/screening-questions");
    }

    private function liveJob(string $title = 'Backend Developer'): int
    {
        return $this->insertJob($title, 'active', attributes: ['deadline' => '2026-12-31']);
    }

    // ----- reading and writing the list of a job -----

    public function test_a_new_job_has_no_questions(): void
    {
        $job = $this->insertJob();

        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_the_list_is_replaced_whole_and_comes_back_in_order(): void
    {
        $job = $this->insertJob();
        $relocate = $this->question('yes_no', 'Can you relocate to Bandung?');
        $years = $this->question('number', 'Years of PHP experience?');
        $city = $this->question('single_choice', 'Where do you live?', ['Jakarta', 'Bandung']);
        $jakarta = $this->optionId($city, 'Jakarta');

        $response = $this->setQuestions($job, [
            ['question_id' => $years, 'required' => true, 'must_have' => true, 'ideal' => ['min' => 3]],
            ['question_id' => $relocate],
            ['question_id' => $city, 'required' => false, 'must_have' => true, 'ideal' => ['option_ids' => [$jakarta]]],
        ])->assertOk();

        $this->assertSame([$years, $relocate, $city], array_column($response->json('data'), 'question_id'));
        $this->assertSame([1, 2, 3], array_column($response->json('data'), 'position'));
        $response->assertJsonPath('data.0.ideal', ['min' => 3, 'max' => null])->assertJsonPath('data.0.must_have', true)
            ->assertJsonPath('data.1.required', false)->assertJsonPath('data.1.must_have', false)->assertJsonPath('data.1.ideal', null)
            ->assertJsonPath('data.2.required', true)->assertJsonPath('data.2.ideal', ['option_ids' => [$jakarta]])->assertJsonPath('data.2.options.0.label', 'Jakarta');

        $this->assertEquals($response->json('data'), $this->api('GET', "/company/jobs/{$job}/screening-questions")->json('data'));

        // Replaced again: one stays (with new flags), one goes, a new one comes.
        $second = $this->setQuestions($job, [['question_id' => $relocate, 'required' => true], ['question_id' => $years]])->assertOk();
        $this->assertSame([$relocate, $years], array_column($second->json('data'), 'question_id'));
        $second->assertJsonPath('data.0.required', true)->assertJsonPath('data.1.must_have', false)->assertJsonPath('data.1.ideal', null);
        $this->assertSame(2, DB::table('recruitment_job_questions')->where('job_id', $job)->count());
        $this->assertSame(0, DB::table('recruitment_job_question_ideal_options')->count());

        $this->setQuestions($job, [])->assertOk()->assertExactJson(['data' => []]);
        $this->assertSame(0, DB::table('recruitment_job_questions')->count());
    }

    public function test_every_ideal_answer_shape_is_stored_and_returned(): void
    {
        $job = $this->insertJob();
        $yes = $this->question('yes_no', 'Do you hold a driving licence?');
        $range = $this->question('number', 'How many years have you worked?');
        $multi = $this->question('multiple_choice', 'Which languages do you speak?', ['Indonesian', 'English', 'Dutch']);
        $english = $this->optionId($multi, 'English');
        $dutch = $this->optionId($multi, 'Dutch');

        $response = $this->setQuestions($job, [
            ['question_id' => $yes, 'must_have' => true, 'ideal' => ['yes' => false]],
            ['question_id' => $range, 'must_have' => true, 'ideal' => ['min' => 1, 'max' => 10]],
            ['question_id' => $multi, 'must_have' => true, 'ideal' => ['option_ids' => [$english, $dutch]]],
        ])->assertOk();

        $response->assertJsonPath('data.0.ideal', ['yes' => false])->assertJsonPath('data.1.ideal', ['min' => 1, 'max' => 10])
            ->assertJsonPath('data.2.ideal', ['option_ids' => [$english, $dutch]]);
        $this->assertTrue(collect($response->json('data'))->every(fn (array $row): bool => $row['required'] === true));
    }

    public function test_a_must_have_question_is_always_required_and_needs_an_ideal_answer(): void
    {
        $job = $this->insertJob();
        $yes = $this->question();
        $text = $this->question('text', 'Why do you want this job?');

        $this->setQuestions($job, [['question_id' => $yes, 'required' => false, 'must_have' => true, 'ideal' => ['yes' => true]]])->assertOk()->assertJsonPath('data.0.required', true);

        $this->setQuestions($job, [['question_id' => $yes, 'must_have' => true]])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-job-questions')->assertJsonStructure(['errors' => ['questions.0.ideal']]);
        $this->setQuestions($job, [['question_id' => $yes, 'must_have' => true, 'ideal' => ['yes' => 'maybe']]])->assertStatus(422)->assertJsonStructure(['errors' => ['questions.0.ideal']]);
        $this->setQuestions($job, [['question_id' => $yes, 'ideal' => ['yes' => true]]])->assertStatus(422)->assertJsonStructure(['errors' => ['questions.0.ideal']]);
        $this->setQuestions($job, [['question_id' => $text, 'must_have' => true, 'ideal' => ['yes' => true]]])->assertStatus(422)->assertJsonStructure(['errors' => ['questions.0.must_have']]);
        $this->setQuestions($job, [['question_id' => $text, 'required' => true]])->assertOk()->assertJsonPath('data.0.type', 'text');
    }

    public function test_the_ideal_options_must_belong_to_the_question(): void
    {
        $job = $this->insertJob();
        $one = $this->question('single_choice', 'Which city do you prefer?', ['A', 'B']);
        $two = $this->question('single_choice', 'Which team do you prefer?', ['C', 'D']);

        $this->setQuestions($job, [['question_id' => $one, 'must_have' => true, 'ideal' => ['option_ids' => [$this->optionId($two, 'C')]]]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['questions.0.ideal']]);
        $this->assertSame(0, DB::table('recruitment_job_questions')->count());
    }

    public function test_all_the_errors_of_the_list_are_reported_together_and_nothing_is_saved(): void
    {
        $job = $this->insertJob();
        $ok = $this->question();
        $foreign = $this->question('yes_no', 'Question of the other company', [], $this->otherAdminToken);
        $archived = $this->question('yes_no', 'An archived question here');
        $this->api('POST', "/company/screening-questions/{$archived}/archive")->assertOk();

        $response = $this->setQuestions($job, [
            ['question_id' => $ok], ['question_id' => $foreign], ['question_id' => $archived], ['question_id' => 999_999],
        ])->assertStatus(422);

        $response->assertJsonStructure(['errors' => ['questions.1.question_id', 'questions.2.question_id', 'questions.3.question_id']]);
        $this->assertArrayNotHasKey('questions.0.question_id', $response->json('errors'));
        $this->assertSame(0, DB::table('recruitment_job_questions')->count());
    }

    public function test_the_shape_of_the_request_is_checked(): void
    {
        $job = $this->insertJob();
        $one = $this->question();

        $this->api('PUT', "/company/jobs/{$job}/screening-questions", [])->assertStatus(422);
        $this->setQuestions($job, [['question_id' => $one], ['question_id' => $one]])->assertStatus(422);
        $this->setQuestions($job, [['required' => true]])->assertStatus(422);
        $this->setQuestions($job, [['question_id' => $one, 'required' => 'sometimes']])->assertStatus(422);
        $this->setQuestions($job, [['question_id' => $one, 'color' => 'red']])->assertStatus(422);
    }

    public function test_an_archived_question_that_a_job_already_uses_stays_and_can_stay_in_the_list(): void
    {
        $job = $this->insertJob();
        $one = $this->question();
        $two = $this->question('yes_no', 'Can you work at weekends?');
        $this->setQuestions($job, [['question_id' => $one], ['question_id' => $two]])->assertOk();
        $this->api('POST', "/company/screening-questions/{$one}/archive")->assertOk();

        $response = $this->setQuestions($job, [['question_id' => $two], ['question_id' => $one, 'required' => true]])->assertOk();

        $this->assertSame([$two, $one], array_column($response->json('data'), 'question_id'));
        $response->assertJsonPath('data.1.archived', true);

        // Once removed, it cannot come back.
        $this->setQuestions($job, [['question_id' => $two]])->assertOk();
        $this->setQuestions($job, [['question_id' => $two], ['question_id' => $one]])->assertStatus(422);
    }

    // ----- who may -----

    public function test_a_recruiter_manages_only_the_jobs_assigned_to_them(): void
    {
        $question = $this->question();
        $mine = $this->insertJob('Mine', 'draft', [$this->recruiterId]);
        $theirs = $this->insertJob('Theirs', 'draft', [$this->adminId]);

        $this->setQuestions($mine, [['question_id' => $question]], $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/jobs/{$mine}/screening-questions", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.question_id', $question);

        $this->setQuestions($theirs, [['question_id' => $question]], $this->recruiterToken)->assertStatus(404);
        $this->api('GET', "/company/jobs/{$theirs}/screening-questions", token: $this->recruiterToken)->assertStatus(404);
        $this->assertSame(1, DB::table('recruitment_job_questions')->count());

        // A company admin manages every job of the company.
        $this->setQuestions($theirs, [['question_id' => $question]])->assertOk();
    }

    public function test_a_job_of_another_company_does_not_exist(): void
    {
        $foreign = $this->insertJob('Foreign', 'draft', [$this->otherAdminId], $this->otherCompanyId);
        $question = $this->question();

        $this->setQuestions($foreign, [['question_id' => $question]])->assertStatus(404);
        $this->api('GET', "/company/jobs/{$foreign}/screening-questions")->assertStatus(404);
        $this->api('GET', '/company/jobs/999999/screening-questions')->assertStatus(404);
    }

    public function test_the_list_needs_a_company_member_of_an_active_company(): void
    {
        $job = $this->insertJob();

        $this->getJson("/api/v1/company/jobs/{$job}/screening-questions")->assertStatus(401);

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);
        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        $this->setQuestions($job, [])->assertStatus(403);
    }

    // ----- when the list cannot change -----

    public function test_a_job_waiting_for_review_or_taken_down_cannot_change(): void
    {
        $question = $this->question();

        foreach (['pending_review', 'taken_down'] as $status) {
            $job = $this->insertJob("In {$status}", $status);
            $this->setQuestions($job, [['question_id' => $question]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-editable');
        }

        foreach (['draft', 'active', 'closed', 'expired', 'rejected'] as $status) {
            $job = $this->insertJob("In {$status}", $status);
            $this->setQuestions($job, [['question_id' => $question]])->assertOk();
        }
    }

    public function test_a_job_with_applications_is_locked(): void
    {
        $job = $this->insertJob();
        $question = $this->question();
        $this->setQuestions($job, [['question_id' => $question]])->assertOk();

        $this->app->instance(JobApplicationCheck::class, new class implements JobApplicationCheck
        {
            public function hasApplications(int $jobId): bool
            {
                return true;
            }
        });

        $this->setQuestions($job, [])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-questions-locked');
        $this->assertSame(1, DB::table('recruitment_job_questions')->count());
        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_limit_refuses_new_questions_but_not_a_list_that_adds_nothing(): void
    {
        $job = $this->insertJob();
        $ids = array_map(fn (int $n): int => $this->question('yes_no', "Question number {$n} to answer"), range(1, 6));

        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], array_slice($ids, 0, 5)))->assertOk()->assertJsonCount(5, 'data');
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], $ids))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:screening-limit-reached');
        $this->assertSame(5, DB::table('recruitment_job_questions')->count());

        // The admin lowers the limit: what is attached stays and can be rearranged or reduced, but nothing new is added.
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $this->companyId, 3, null);

        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id, 'required' => true], array_reverse(array_slice($ids, 0, 5))))->assertOk()->assertJsonCount(5, 'data');
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], array_slice($ids, 0, 4)))->assertOk()->assertJsonCount(4, 'data');
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], [...array_slice($ids, 0, 3), $ids[5]]))->assertStatus(409);
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], [$ids[3], $ids[4], $ids[5], $ids[0]]))->assertStatus(409);
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], array_slice($ids, 0, 3)))->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_while_screening_is_off_the_list_can_be_read_but_not_changed(): void
    {
        $job = $this->insertJob();
        $question = $this->question();
        $this->setQuestions($job, [['question_id' => $question]])->assertOk();
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, false, null);

        $this->setQuestions($job, [])->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:screening-disabled');
        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_question_that_a_job_uses_cannot_be_deleted_until_it_is_detached(): void
    {
        $job = $this->insertJob();
        $question = $this->question('single_choice', 'Where can you work?', ['Jakarta', 'Bandung']);
        $this->setQuestions($job, [['question_id' => $question, 'must_have' => true, 'ideal' => ['option_ids' => [$this->optionId($question, 'Jakarta')]]]])->assertOk();

        $this->api('DELETE', "/company/screening-questions/{$question}")->assertStatus(409);
        $this->setQuestions($job, [])->assertOk();
        $this->api('DELETE', "/company/screening-questions/{$question}")->assertNoContent();
        $this->assertSame(0, DB::table('recruitment_job_question_ideal_options')->count());
    }

    // ----- the public side -----

    public function test_the_public_sees_the_questions_of_a_live_job_without_the_secret_parts(): void
    {
        $job = $this->liveJob();
        $city = $this->question('single_choice', 'Where do you live?', ['Jakarta', 'Bandung']);
        $years = $this->question('number', 'Years of PHP experience?');
        $note = $this->question('text', 'Anything you want us to know?');
        $this->setQuestions($job, [
            ['question_id' => $years, 'must_have' => true, 'ideal' => ['min' => 3]],
            ['question_id' => $city, 'required' => true, 'must_have' => true, 'ideal' => ['option_ids' => [$this->optionId($city, 'Jakarta')]]],
            ['question_id' => $note],
        ])->assertOk();

        $response = $this->publicQuestions((string) $job)->assertOk();

        $this->assertSame([$years, $city, $note], array_column($response->json('data'), 'id'));
        $this->assertSame([1, 2, 3], array_column($response->json('data'), 'position'));
        $this->assertSame([true, true, false], array_column($response->json('data'), 'required'));
        $response->assertJsonPath('data.1.options.1.label', 'Bandung')->assertJsonPath('data.0.text', 'Years of PHP experience?');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('must_have', $body);
        $this->assertStringNotContainsString('ideal', $body);
        $this->assertStringContainsString('max-age=60', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));
        $this->publicQuestions("{$job}-backend-developer")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_job_that_is_not_public_has_no_public_questions(): void
    {
        $question = $this->question();

        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $job = $this->insertJob("Job {$status}", $status, attributes: ['deadline' => '2026-12-31']);
            $this->setQuestions($job, [['question_id' => $question]]);
            $this->publicQuestions((string) $job)->assertStatus(404);
        }

        $late = $this->insertJob('Deadline passed', 'active', attributes: ['deadline' => '2026-10-04']);
        $this->publicQuestions((string) $late)->assertStatus(404);
        $this->publicQuestions('999999')->assertStatus(404);
        $this->publicQuestions('12abc')->assertStatus(404);
    }

    public function test_a_suspended_company_hides_the_questions_of_its_jobs(): void
    {
        $job = $this->liveJob();
        $this->setQuestions($job, [['question_id' => $this->question()]])->assertOk();
        $this->publicQuestions((string) $job)->assertOk()->assertJsonCount(1, 'data');

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->publicQuestions((string) $job)->assertStatus(404);
    }

    public function test_while_screening_is_off_the_public_list_is_empty_and_comes_back_when_it_is_on(): void
    {
        $job = $this->liveJob();
        $this->setQuestions($job, [['question_id' => $this->question()]])->assertOk();
        $store = $this->app->make(SettingsStore::class);

        $store->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, false, null);
        $this->publicQuestions((string) $job)->assertOk()->assertExactJson(['data' => []]);

        $store->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, true, null);
        $this->publicQuestions((string) $job)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_archived_question_still_shows_on_the_jobs_that_use_it(): void
    {
        $job = $this->liveJob();
        $question = $this->question();
        $this->setQuestions($job, [['question_id' => $question]])->assertOk();
        $this->api('POST', "/company/screening-questions/{$question}/archive")->assertOk();

        $this->publicQuestions((string) $job)->assertOk()->assertJsonPath('data.0.id', $question);
    }
}
