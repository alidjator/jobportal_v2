<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-results.md`: the comparison of interview results, the scale anchors, duplicating a kit and the interview columns of the export. */
final class InterviewResultsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    /** @var array<string, int> the application ids by candidate name */
    private array $applications = [];

    /** @var array<string, string> the job seeker tokens by candidate name */
    private array $seekers = [];

    /** @var array<string, int> the interview ids by candidate name */
    private array $interviews = [];

    private int $hours = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return list<array<string, mixed>> */
    private function questions(): array
    {
        return [
            ['text' => 'Explain a database transaction.', 'guidance' => 'Look for isolation levels.', 'weight' => 5, 'anchor_1' => 'Cannot explain it', 'anchor_5' => 'Explains isolation levels'],
            ['text' => 'Tell us about a hard bug.', 'weight' => 3],
        ];
    }

    private function createKit(string $name = 'Backend round', ?array $questions = null): int
    {
        return (int) $this->api('POST', '/company/interview-kits', ['name' => $name, 'questions' => $questions ?? $this->questions()], $this->recruiterToken)->assertCreated()->json('data.id');
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** A job seeker applies to the job; nothing is scheduled yet. */
    private function candidate(string $name): void
    {
        $id = $this->seeker('candidate.'.strtolower($name).'@example.test', name: $name);
        $token = $this->tokenFor($id);
        $resume = $this->completeProfile($token);

        $this->seekers[$name] = $token;
        $this->applications[$name] = (int) $this->apply($this->job, ['resume_id' => $resume], $token)->assertCreated()->json('data.id');
    }

    /** Schedules a confirmed interview with the kit (the recruiter interviews, the second recruiter sits in), two hours after the one before. */
    private function interview(string $name, int $kit): void
    {
        $start = now()->addDays(3)->addHours($this->hours);
        $this->hours += 2;

        $schedule = $this->interviewSchedule(['kit_id' => $kit, 'participant_ids' => [$this->secondRecruiterId], 'starts_at' => $start->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->api('POST', "/company/applications/{$this->applications[$name]}/move", ['stage_id' => $this->stage('interview'), 'interview' => $schedule], $this->recruiterToken)->assertOk();
        $this->api('POST', "/me/applications/{$this->applications[$name]}/interview/confirm", [], $this->seekers[$name])->assertOk();

        $this->interviews[$name] = (int) DB::table('recruitment_interviews')->where('application_id', $this->applications[$name])->value('id');
    }

    /** The clock moves past every interview and the tokens are issued again, since they expire after a day. */
    private function startScoring(): void
    {
        Carbon::setTestNow('2026-10-08 20:00:00');
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->secondRecruiterToken = $this->tokenFor($this->secondRecruiterId);
        $this->otherAdminToken = $this->tokenFor($this->otherAdminId);
    }

    /** A panel member sends a scorecard that gives both questions of the interview a score. */
    private function sendCard(string $name, string $token, string $recommendation, int $first, int $second): void
    {
        $interview = $this->interviews[$name];
        $ids = DB::table('recruitment_interview_questions')->where('interview_id', $interview)->orderBy('position')->pluck('id')->all();

        $this->api('PUT', "/company/interviews/{$interview}/scorecard", ['recommendation' => $recommendation, 'answers' => [
            ['question_id' => $ids[0], 'score' => $first],
            ['question_id' => $ids[1], 'score' => $second],
        ]], $token)->assertOk();
        $this->api('POST', "/company/interviews/{$interview}/scorecard/submit", [], $token)->assertOk();
    }

    /**
     * Ani: yes from the recruiter (4, 4) and no from the second recruiter (2, 2), so the result is 3.00. Budi: yes from both (5, 5), so 5.00. Citra: no from the
     * second recruiter only (2, 2), so it is locked for the recruiter. Dewi has no interview.
     */
    private function scoredJob(): int
    {
        foreach (['Ani', 'Budi', 'Citra', 'Dewi'] as $name) {
            $this->candidate($name);
        }

        $kit = $this->createKit();

        foreach (['Ani', 'Budi', 'Citra'] as $name) {
            $this->interview($name, $kit);
        }

        $this->startScoring();
        $this->sendCard('Ani', $this->recruiterToken, 'yes', 4, 4);
        $this->sendCard('Ani', $this->secondRecruiterToken, 'no', 2, 2);
        $this->sendCard('Budi', $this->recruiterToken, 'strong_yes', 5, 5);
        $this->sendCard('Budi', $this->secondRecruiterToken, 'yes', 5, 5);
        $this->sendCard('Citra', $this->secondRecruiterToken, 'no', 2, 2);

        return $kit;
    }

    private function compare(string $query = '', ?string $token = null, ?int $job = null): TestResponse
    {
        return $this->api('GET', '/company/jobs/'.($job ?? $this->job).'/interview-results'.$query, [], $token ?? $this->adminToken);
    }

    /** @return list<string> the candidate names of the rows, in order */
    private function names(TestResponse $response): array
    {
        return array_map(static fn (array $row): string => $row['application']['name'], $response->json('data'));
    }

    // ----- section 2: scale anchors -----

    public function test_anchors_are_saved_with_a_kit_and_copied_into_the_interview(): void
    {
        $kit = $this->createKit();

        $this->api('GET', "/company/interview-kits/{$kit}", [], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.questions.0.anchors', ['score_1' => 'Cannot explain it', 'score_3' => null, 'score_5' => 'Explains isolation levels'])
            ->assertJsonPath('data.questions.1.anchors', ['score_1' => null, 'score_3' => null, 'score_5' => null]);

        $this->candidate('Ani');
        $this->interview('Ani', $kit);

        $this->api('GET', "/company/interviews/{$this->interviews['Ani']}/questions", [], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.questions.0.anchors.score_5', 'Explains isolation levels');

        $copy = [['text' => 'Explain a database transaction.', 'weight' => 5, 'anchor_3' => 'Knows the basics']];
        $this->api('PUT', "/company/interviews/{$this->interviews['Ani']}/questions", ['questions' => $copy], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.questions.0.anchors.score_3', 'Knows the basics');
    }

    public function test_an_anchor_is_trimmed_and_an_empty_one_is_cleared(): void
    {
        $kit = $this->createKit('Round', [['text' => 'One?', 'weight' => 1, 'anchor_1' => '  Weak  ', 'anchor_3' => '   ', 'anchor_5' => null]]);

        $this->api('GET', "/company/interview-kits/{$kit}", [], $this->recruiterToken)
            ->assertJsonPath('data.questions.0.anchors', ['score_1' => 'Weak', 'score_3' => null, 'score_5' => null]);
    }

    public function test_an_anchor_over_200_characters_is_refused_with_its_field(): void
    {
        $body = ['name' => 'Round', 'questions' => [['text' => 'One?', 'weight' => 1, 'anchor_3' => str_repeat('a', 201)]]];

        $this->api('POST', '/company/interview-kits', $body, $this->recruiterToken)
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-kit')
            ->assertJsonPath('errors', ['questions.0.anchor_3' => ['An anchor can have at most 200 characters.']]);
    }

    // ----- section 3: duplicating a kit -----

    public function test_a_kit_is_duplicated_with_its_questions_under_a_free_name(): void
    {
        $kit = $this->createKit('Backend round');

        $first = $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->recruiterToken)->assertCreated()
            ->assertJsonPath('data.name', 'Backend round (salinan)')
            ->assertJsonPath('data.questions.0.guidance', 'Look for isolation levels.')
            ->assertJsonPath('data.questions.0.weight', 5)
            ->assertJsonPath('data.questions.0.anchors.score_5', 'Explains isolation levels')
            ->assertJsonPath('data.questions.1.text', 'Tell us about a hard bug.');
        $this->assertNotSame($kit, $first->json('data.id'));

        $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->recruiterToken)->assertCreated()->assertJsonPath('data.name', 'Backend round (salinan 2)');
        $this->api('POST', '/company/interview-kits/'.$first->json('data.id').'/duplicate', [], $this->recruiterToken)->assertCreated()->assertJsonPath('data.name', 'Backend round (salinan) (salinan)');
    }

    public function test_a_duplicated_name_stays_within_100_characters_and_ignores_case(): void
    {
        $long = str_repeat('x', 100);
        $kit = $this->createKit($long);

        $name = $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->recruiterToken)->assertCreated()->json('data.name');
        $this->assertSame(100, mb_strlen($name));
        $this->assertStringEndsWith(' (salinan)', $name);

        $this->createKit('Round');
        $this->createKit('ROUND (SALINAN)');
        $other = (int) DB::table('recruitment_interview_kits')->where('name', 'Round')->value('id');
        $this->api('POST', "/company/interview-kits/{$other}/duplicate", [], $this->recruiterToken)->assertCreated()->assertJsonPath('data.name', 'Round (salinan 2)');
    }

    public function test_a_duplicate_does_not_copy_the_job_defaults_and_counts_toward_the_kit_limit(): void
    {
        $kit = $this->createKit();
        $this->api('PUT', "/company/jobs/{$this->job}/interview-kits/{$this->stage('interview')}", ['kit_id' => $kit], $this->recruiterToken)->assertOk();

        $copy = (int) $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->recruiterToken)->assertCreated()->json('data.id');
        $this->assertSame(0, DB::table('recruitment_job_stage_kits')->where('kit_id', $copy)->count());

        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_INTERVIEW_KITS, $this->companyId, 2, null);
        $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:kit-limit-reached');
    }

    public function test_only_the_own_kits_of_a_member_with_the_permission_are_duplicated(): void
    {
        $kit = $this->createKit();

        $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->otherAdminToken)->assertNotFound();
        $this->api('POST', "/company/interview-kits/{$kit}/duplicate", [], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->api('POST', '/company/interview-kits/999999/duplicate', [], $this->recruiterToken)->assertNotFound();
    }

    // ----- section 1: the comparison -----

    public function test_the_comparison_ranks_the_candidates_by_the_result_of_their_latest_scored_interview(): void
    {
        $kit = $this->scoredJob();

        $response = $this->compare()->assertOk();

        // Dewi has no interview and is left out; Citra has a result only the admin may see.
        $this->assertSame(['Budi', 'Ani', 'Citra'], $this->names($response));
        $response->assertJsonPath('kit.id', $kit)->assertJsonPath('kit.name', 'Backend round')
            ->assertJsonPath('kit.questions.0.anchors.score_5', 'Explains isolation levels')
            ->assertJsonPath('meta.total', 3);

        $ani = $response->json('data.1');
        $this->assertSame($this->applications['Ani'], $ani['application']['id']);
        $this->assertSame($this->interviews['Ani'], $ani['interview']['id']);
        $this->assertSame(['id' => $kit, 'name' => 'Backend round'], $ani['interview']['kit']);
        $this->assertFalse($ani['locked']);
        $this->assertEquals(3.0, $ani['score']);
        $this->assertSame(['strong_no' => 0, 'no' => 1, 'yes' => 1, 'strong_yes' => 0], $ani['recommendations']);
        $this->assertSame(2, $ani['scorecards_sent']);
        $this->assertSame(2, $ani['panel_size']);
        $this->assertEquals([
            ['question' => 'Explain a database transaction.', 'average' => 3.0, 'count' => 2],
            ['question' => 'Tell us about a hard bug.', 'average' => 3.0, 'count' => 2],
        ], $ani['cells']);
    }

    public function test_the_comparison_can_be_sorted_by_yes_recommendations_and_match_score(): void
    {
        $this->scoredJob();
        DB::table('recruitment_applications')->where('id', $this->applications['Ani'])->update(['match_score' => 90]);
        DB::table('recruitment_applications')->where('id', $this->applications['Budi'])->update(['match_score' => 40]);

        $this->assertSame(['Budi', 'Ani', 'Citra'], $this->names($this->compare('?sort=yes')->assertOk()));
        $this->assertSame(['Ani', 'Budi', 'Citra'], $this->names($this->compare('?sort=match_score')->assertOk()));
        $this->assertSame(['Citra'], $this->names($this->compare('?per_page=1&page=3')->assertOk()));
        $this->compare('?sort=name')->assertStatus(422);
        $this->compare('?per_page=101')->assertStatus(422);
    }

    public function test_a_row_is_locked_while_the_caller_is_on_the_panel_without_a_sent_scorecard(): void
    {
        $this->scoredJob();

        $row = $this->compare('', $this->recruiterToken)->assertOk()->json('data');
        $this->assertSame(['Budi', 'Ani', 'Citra'], array_map(static fn (array $item): string => $item['application']['name'], $row));

        // The recruiter sent for Ani and Budi but not for Citra.
        $citra = $row[2];
        $this->assertTrue($citra['locked']);
        $this->assertSame($this->interviews['Citra'], $citra['interview']['id']);
        $this->assertNull($citra['score']);
        $this->assertNull($citra['recommendations']);
        $this->assertNull($citra['scorecards_sent']);
        $this->assertNull($citra['cells']);
        $this->assertFalse($row[0]['locked']);
    }

    public function test_the_columns_follow_the_requested_kit_and_match_a_question_by_its_trimmed_text_without_case(): void
    {
        $kit = $this->scoredJob();

        // The interviews were scheduled with the first kit, so no question of another kit is a column for them.
        $other = $this->createKit('Other round', [['text' => 'Explain a database transaction.', 'weight' => 2]]);
        $response = $this->compare("?filter[kit_id]={$other}")->assertOk()->assertJsonPath('kit.id', $other);
        $this->assertEquals([['question' => 'Explain a database transaction.', 'average' => null, 'count' => 0]], $response->json('data.0.cells'));

        // The kit was reworded after the interviews: the same words in another case still fill the column, a new question has no score.
        $this->api('PUT', "/company/interview-kits/{$kit}", ['name' => 'Backend round', 'questions' => [
            ['text' => ' EXPLAIN A DATABASE TRANSACTION. ', 'weight' => 5],
            ['text' => 'Something new?', 'weight' => 1],
        ]], $this->recruiterToken)->assertOk();

        $cells = $this->compare()->assertOk()->json('data.1.cells');
        $this->assertEquals([['question' => 'EXPLAIN A DATABASE TRANSACTION.', 'average' => 3.0, 'count' => 2], ['question' => 'Something new?', 'average' => null, 'count' => 0]], $cells);
    }

    public function test_the_most_used_kit_is_the_default_columns(): void
    {
        $first = $this->createKit('First');
        $second = $this->createKit('Second');

        foreach (['Ani', 'Budi', 'Citra'] as $name) {
            $this->candidate($name);
        }

        $this->interview('Ani', $first);
        $this->interview('Budi', $second);
        $this->interview('Citra', $second);
        $this->startScoring();

        foreach (['Ani', 'Budi', 'Citra'] as $name) {
            $this->sendCard($name, $this->secondRecruiterToken, 'yes', 4, 4);
        }

        $this->compare()->assertOk()->assertJsonPath('kit.id', $second);
        $this->compare("?filter[kit_id]={$first}")->assertOk()->assertJsonPath('kit.id', $first);
    }

    public function test_the_comparison_is_limited_to_a_stage_and_final_stages_are_opened_on_request(): void
    {
        $this->scoredJob();

        $interview = $this->stage('interview');
        $this->compare("?filter[stage_id]={$interview}")->assertOk()->assertJsonPath('meta.total', 3);

        $this->api('POST', "/company/applications/{$this->applications['Budi']}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->assertSame(['Ani', 'Citra'], $this->names($this->compare()->assertOk()));
        $this->assertSame(['Budi'], $this->names($this->compare('?filter[stage_type]=rejected')->assertOk()));
    }

    public function test_the_comparison_refuses_a_stage_that_is_not_an_interview_stage_and_unknown_filters(): void
    {
        $this->scoredJob();

        $this->compare('?filter[stage_id]='.$this->stage('applied'))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:stage-not-interview');
        $this->compare('?filter[stage_id]=999999')->assertNotFound();
        $this->compare('?filter[kit_id]=999999')->assertNotFound();
        $this->compare('?filter[name]=a')->assertStatus(422);
        $this->compare('?filter[stage_type]=nope')->assertStatus(422);
    }

    public function test_the_comparison_is_for_members_who_see_the_job(): void
    {
        $this->scoredJob();
        $unassigned = $this->insertJob('Other job', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);

        $this->compare('', $this->tokenFor($this->seekerId))->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->compare('', $this->otherAdminToken)->assertNotFound();
        $this->compare('', $this->recruiterToken, $unassigned)->assertNotFound();
        $this->compare('', null, 999999)->assertNotFound();
        $this->compare('', $this->secondRecruiterToken, $unassigned)->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('kit', null);
    }

    public function test_an_anonymised_applicant_shows_as_deleted_user_with_the_scores(): void
    {
        $this->scoredJob();
        DB::table('recruitment_applications')->where('id', $this->applications['Budi'])->update(['profile_snapshot' => null]);

        $row = $this->compare()->assertOk()->json('data.0');
        $this->assertSame($this->applications['Budi'], $row['application']['id']);
        $this->assertTrue($row['application']['is_deleted']);
        $this->assertSame('Deleted user', $row['application']['name']);
        $this->assertEquals(5.0, $row['score']);
    }

    // ----- section 4: interview scores in the export -----

    /** @return array<string, array<string, string>> the export lines by candidate name, by column header */
    private function exported(?string $token = null): array
    {
        $response = $this->api('GET', "/company/jobs/{$this->job}/applications/export", [], $token ?? $this->adminToken)->assertOk();
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($response->streamedContent(), 3));
        rewind($stream);

        $header = fgetcsv($stream, escape: '');
        $rows = [];

        while (($line = fgetcsv($stream, escape: '')) !== false) {
            $row = array_combine($header, $line);
            $rows[$row['Name']] = $row;
        }

        return $rows;
    }

    public function test_the_export_holds_the_interview_score_the_yes_and_no_counts_and_the_kit(): void
    {
        $this->scoredJob();

        $rows = $this->exported();

        $this->assertSame(['3.00', '1', '1', 'Backend round'], [$rows['Ani']['Interview score'], $rows['Ani']['Interview yes'], $rows['Ani']['Interview no'], $rows['Ani']['Interview kit']]);
        $this->assertSame(['5.00', '2', '0', 'Backend round'], [$rows['Budi']['Interview score'], $rows['Budi']['Interview yes'], $rows['Budi']['Interview no'], $rows['Budi']['Interview kit']]);
        $this->assertSame(['', '', '', ''], [$rows['Dewi']['Interview score'], $rows['Dewi']['Interview yes'], $rows['Dewi']['Interview no'], $rows['Dewi']['Interview kit']]);
    }

    public function test_the_export_leaves_the_interview_cells_empty_where_the_exporting_member_is_locked(): void
    {
        $this->scoredJob();

        $rows = $this->exported($this->recruiterToken);

        $this->assertSame(['', '', '', ''], [$rows['Citra']['Interview score'], $rows['Citra']['Interview yes'], $rows['Citra']['Interview no'], $rows['Citra']['Interview kit']]);
        $this->assertSame('3.00', $rows['Ani']['Interview score']);
        $this->assertSame('2.00', $this->exported($this->adminToken)['Citra']['Interview score']);
    }
}
