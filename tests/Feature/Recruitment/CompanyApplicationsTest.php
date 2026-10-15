<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class CompanyApplicationsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $firstId;

    private int $secondId;

    private int $budiId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->verifyTheCompany();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        // Siti applies first, Budi an hour later.
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->firstId = (int) $this->apply($this->job, ['cover_letter' => 'Please hire me.'])->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->budiId = $this->seeker('budi.s@example.test', name: 'Budi Santoso');
        $budiToken = $this->tokenFor($this->budiId);
        $budiResume = $this->completeProfile($budiToken);
        $this->secondId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $budiResume], $budiToken)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function list(array|string $query = '', ?string $token = null, ?int $job = null): TestResponse
    {
        $query = is_array($query) ? '?'.http_build_query($query) : $query;

        return $this->api('GET', '/company/jobs/'.($job ?? $this->job).'/applications'.$query, token: $token ?? $this->adminToken);
    }

    private function show(int $id, ?string $token = null): TestResponse
    {
        return $this->api('GET', "/company/applications/{$id}", token: $token ?? $this->adminToken);
    }

    // ----- access -----

    public function test_only_company_members_who_may_see_the_job_can_read_its_applications(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson("/api/v1/company/jobs/{$this->job}/applications")->assertStatus(401);
        $this->getJson("/api/v1/company/applications/{$this->firstId}")->assertStatus(401);

        // A company admin sees every job, a recruiter only the jobs assigned to them.
        $this->list()->assertOk()->assertJsonCount(2, 'data');
        $this->list(token: $this->recruiterToken)->assertOk()->assertJsonCount(2, 'data');
        $this->show($this->firstId, $this->recruiterToken)->assertOk();

        foreach ([$this->secondRecruiterToken, $this->otherAdminToken] as $token) {
            $this->list(token: $token)->assertStatus(404);
            $this->show($this->firstId, $token)->assertStatus(404);
            $this->api('GET', "/company/applications/{$this->firstId}/resume", token: $token)->assertStatus(404);
        }

        $this->list(token: $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->show($this->firstId, $this->seekerToken)->assertStatus(403);
        $this->show(999999)->assertStatus(404);
        $this->list(job: 999999)->assertStatus(404);
    }

    public function test_a_suspended_company_cannot_read_its_applications(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->list()->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->show($this->firstId)->assertStatus(403);
    }

    // ----- the list -----

    public function test_the_list_shows_the_applicants_newest_first_without_contact_data(): void
    {
        $response = $this->list()->assertOk();

        $this->assertSame([$this->secondId, $this->firstId], array_column($response->json('data'), 'id'));

        $row = $response->json('data.1');
        $this->assertSame(['id', 'applicant', 'meets_must_have', 'rating', 'interview_result', 'stage', 'applied_at'], array_keys($row));
        $this->assertSame(['is_deleted', 'name', 'headline', 'latest_experience', 'city', 'expected_salary', 'age'], array_keys($row['applicant']));
        $this->assertFalse($row['applicant']['is_deleted']);
        $this->assertSame('Siti Rahma', $row['applicant']['name']);
        $this->assertSame('Backend developer', $row['applicant']['headline']);
        $this->assertSame(['title' => 'Backend Developer', 'company' => 'PT Lama'], $row['applicant']['latest_experience']);
        $this->assertSame('BANDUNG', $row['applicant']['city']);
        $this->assertSame(28, $row['applicant']['age']);
        $this->assertNull($row['meets_must_have']);
        $this->assertSame('applied', $row['stage']['type']);
        $this->assertSame('Applied', $row['stage']['name']);
        $this->assertSame('2026-10-05T09:00:00+00:00', $row['applied_at']);

        $body = (string) json_encode($response->json());
        $this->assertStringNotContainsString('siti@example.test', $body);
        $this->assertStringNotContainsString('081234567890', $body);
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_the_list_carries_every_stage_with_its_count_whatever_the_filters(): void
    {
        $interview = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        DB::table('recruitment_applications')->where('id', $this->secondId)->update(['stage_id' => $interview, 'stage_type' => 'interview']);

        foreach ([[], ['filter' => ['stage_type' => 'interview']], ['filter' => ['q' => 'nobody']]] as $query) {
            $stages = $this->list($query)->assertOk()->json('stages');

            $this->assertSame(['applied', 'screening', 'assessment', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'], array_column($stages, 'type'));
            $this->assertSame([1, 0, 0, 1, 0, 0, 0, 0], array_column($stages, 'count'));
        }

        // Another job's applications are not counted.
        $other = $this->insertJob('Other job', 'active', attributes: ['deadline' => '2026-12-31']);
        $this->apply($other)->assertCreated();
        $this->assertSame(2, array_sum(array_column($this->list()->json('stages'), 'count')));
    }

    public function test_the_list_filters_by_stage_must_have_and_name(): void
    {
        $interview = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        DB::table('recruitment_applications')->where('id', $this->secondId)->update(['stage_id' => $interview, 'stage_type' => 'interview', 'meets_must_have' => 0]);
        DB::table('recruitment_applications')->where('id', $this->firstId)->update(['meets_must_have' => 1]);

        $ids = fn (TestResponse $response): array => array_column($response->assertOk()->json('data'), 'id');

        $this->assertSame([$this->secondId], $ids($this->list(['filter' => ['stage_type' => 'interview']])));
        $this->assertSame([$this->secondId], $ids($this->list(['filter' => ['stage_id' => $interview]])));
        $this->assertSame([$this->firstId], $ids($this->list(['filter' => ['meets_must_have' => 'true']])));
        $this->assertSame([$this->secondId], $ids($this->list(['filter' => ['meets_must_have' => 'false']])));
        $this->assertSame([$this->secondId], $ids($this->list(['filter' => ['q' => 'budi']])));
        $this->assertSame([$this->secondId, $this->firstId], $ids($this->list(['filter' => ['q' => 'S']])));
        $this->assertSame([], $ids($this->list(['filter' => ['q' => '%']])));
        $this->assertSame([], $ids($this->list(['filter' => ['stage_type' => 'offer']])));

        $this->list(['filter' => ['stage_type' => 'unknown']])->assertStatus(422);
        $this->list(['filter' => ['company_id' => 1]])->assertStatus(422);
        $this->list(['sort' => 'name'])->assertStatus(422);
    }

    public function test_the_list_sorts_and_pages_by_offset_with_a_total(): void
    {
        $this->assertSame([$this->firstId, $this->secondId], array_column($this->list(['sort' => 'applied_at'])->assertOk()->json('data'), 'id'));

        $page = $this->list(['per_page' => 1, 'page' => 2])->assertOk();
        $this->assertSame([$this->firstId], array_column($page->json('data'), 'id'));
        $page->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.last_page', 2);
        $this->list(['per_page' => 101])->assertStatus(422);
    }

    public function test_an_applicant_whose_account_was_anonymised_shows_as_deleted(): void
    {
        DB::table('recruitment_applications')->where('id', $this->firstId)->update(['profile_snapshot' => null, 'cover_letter' => null, 'resume_name' => null, 'resume_path' => null, 'resume_size_bytes' => null]);

        $row = collect($this->list()->assertOk()->json('data'))->firstWhere('id', $this->firstId);
        $this->assertSame(['is_deleted' => true, 'name' => null, 'headline' => null, 'latest_experience' => null, 'city' => null, 'expected_salary' => null, 'age' => null], $row['applicant']);

        $this->assertSame([], array_column($this->list(['filter' => ['q' => 'Siti']])->json('data'), 'id'));

        $this->show($this->firstId)->assertOk()->assertJsonPath('data.applicant', ['is_deleted' => true, 'name' => null])->assertJsonPath('data.resume', null);
        $this->api('GET', "/company/applications/{$this->firstId}/resume")->assertStatus(404);
    }

    // ----- the detail -----

    public function test_the_detail_shows_the_whole_snapshot_the_cover_letter_and_the_history(): void
    {
        $response = $this->show($this->firstId)->assertOk();

        $response->assertJsonPath('data.job', ['id' => $this->job, 'title' => 'Backend Developer'])
            ->assertJsonPath('data.stage.name', 'Applied')
            ->assertJsonPath('data.cover_letter', 'Please hire me.')
            ->assertJsonPath('data.resume', ['name' => 'Siti CV.pdf'])
            ->assertJsonPath('data.applicant.is_deleted', false)
            ->assertJsonPath('data.applicant.name', 'Siti Rahma')
            ->assertJsonPath('data.applicant.email', 'siti@example.test')
            ->assertJsonPath('data.applicant.phone', '081234567890')
            ->assertJsonPath('data.applicant.experiences.0.job_title', 'Backend Developer')
            ->assertJsonPath('data.history.0.from', null)
            ->assertJsonPath('data.history.0.to.type', 'applied')
            ->assertJsonPath('data.history.0.to.name', 'Applied')
            ->assertJsonPath('data.history.0.by', 'applicant')
            ->assertJsonPath('data.history.0.reason', null);

        foreach (['gender', 'religion', 'birth_date', 'version'] as $field) {
            $this->assertArrayNotHasKey($field, $response->json('data.applicant'));
        }
    }

    public function test_the_history_names_the_stages_and_the_team_member_who_moved_the_application(): void
    {
        $interview = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $applied = (int) DB::table('recruitment_applications')->where('id', $this->firstId)->value('stage_id');
        DB::table('recruitment_applications')->where('id', $this->firstId)->update(['stage_id' => $interview, 'stage_type' => 'interview']);
        DB::table('recruitment_application_stage_history')->insert([
            ['application_id' => $this->firstId, 'from_stage_id' => $applied, 'to_stage_id' => $interview, 'from_type' => 'applied', 'to_type' => 'interview', 'actor_kind' => 'recruiter', 'actor_id' => $this->recruiterId, 'rejection_reason' => null, 'note' => null, 'created_at' => '2026-10-05 11:00:00'],
            ['application_id' => $this->firstId, 'from_stage_id' => $interview, 'to_stage_id' => 987654, 'from_type' => 'interview', 'to_type' => 'rejected', 'actor_kind' => 'recruiter', 'actor_id' => $this->adminId, 'rejection_reason' => 'position_filled', 'note' => 'Filled internally', 'created_at' => '2026-10-05 12:00:00'],
        ]);

        $history = $this->show($this->firstId)->assertOk()->json('data.history');

        $this->assertCount(3, $history);
        $this->assertSame(['id' => $this->recruiterId, 'name' => 'Rina Rekruter'], $history[1]['actor']);
        $this->assertSame('Interview', $history[1]['to']['name']);
        $this->assertSame('Applied', $history[1]['from']['name']);
        $this->assertNull($history[1]['reason']);
        $this->assertSame('position_filled', $history[2]['reason']);
        $this->assertSame('Filled internally', $history[2]['note']);
        $this->assertNull($history[2]['to']['name']);
        $this->assertSame('rejected', $history[2]['to']['type']);
        $this->assertSame(['id' => $this->adminId, 'name' => 'Andi Admin'], $history[2]['actor']);
        $this->assertNull($history[0]['actor']);
    }

    public function test_the_detail_shows_the_answers_with_their_questions(): void
    {
        $job = $this->insertJob('Second job', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $relocate = $this->question('yes_no', 'Can you relocate?');
        $tools = $this->question('multiple_choice', 'Which tools?', ['Git', 'Docker']);
        $this->setQuestions($job, [['question_id' => $relocate, 'must_have' => true, 'ideal' => ['yes' => true]], ['question_id' => $tools]]);
        $docker = $this->optionId($tools, 'Docker');

        $id = (int) $this->api('POST', "/jobs/{$job}/applications", ['resume_id' => $this->resumeId, 'answers' => [
            ['question_id' => $relocate, 'value' => false], ['question_id' => $tools, 'value' => [$docker]],
        ]], $this->seekerToken)->assertCreated()->json('data.id');

        $response = $this->show($id)->assertOk();

        $response->assertJsonPath('data.meets_must_have', false)
            ->assertJsonCount(2, 'data.answers')
            ->assertJsonPath('data.answers.0.question.text', 'Can you relocate?')
            ->assertJsonPath('data.answers.0.bool', false)
            ->assertJsonPath('data.answers.1.options', [['id' => $docker, 'label' => 'Docker']]);
        $this->assertSame(0, collect($this->list(['filter' => ['meets_must_have' => 'true']], job: $job)->json('data'))->count());
    }

    public function test_a_withdrawn_application_stays_readable_with_its_note(): void
    {
        $this->api('POST', "/me/applications/{$this->firstId}/withdraw", ['note' => 'Moved to Surabaya'], $this->seekerToken)->assertNoContent();

        $this->show($this->firstId)->assertOk()->assertJsonPath('data.stage.type', 'withdrawn')->assertJsonPath('data.withdrawn_note', 'Moved to Surabaya')->assertJsonCount(2, 'data.history');
        $this->list(['filter' => ['stage_type' => 'withdrawn']])->assertOk()->assertJsonCount(1, 'data');
    }

    // ----- the resume -----

    public function test_a_member_can_download_the_resume_copy(): void
    {
        $response = $this->api('GET', "/company/applications/{$this->firstId}/resume", token: $this->recruiterToken)->assertOk();

        $this->assertSame($this->pdf(), $response->getContent());
        $response->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "default-src 'none'");
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Siti CV.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $entry = DB::table('platform_audit_logs')->where('action', 'application.resume_downloaded')->first();
        $this->assertNotNull($entry);
        $this->assertSame('application', $entry->subject_type);
        $this->assertSame($this->firstId, (int) $entry->subject_id);
        $this->assertNotNull($entry->company_id);
    }

    public function test_a_missing_resume_copy_is_not_found(): void
    {
        Storage::delete((string) DB::table('recruitment_applications')->where('id', $this->firstId)->value('resume_path'));

        $this->api('GET', "/company/applications/{$this->firstId}/resume")->assertStatus(404);
    }
}
