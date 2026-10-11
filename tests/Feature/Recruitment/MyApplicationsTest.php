<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class MyApplicationsTest extends FeatureTestCase
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

    private function mine(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, '/me/applications'.$uri, $body, $token ?? $this->seekerToken);
    }

    /** Applies to a new live job and returns the application id. */
    private function applyTo(string $title = 'Backend Developer', array $body = []): int
    {
        $job = $this->insertJob($title, 'active', attributes: ['deadline' => '2026-12-31']);

        return (int) $this->apply($job, $body)->assertCreated()->json('data.id');
    }

    // ----- access -----

    public function test_every_route_needs_a_signed_in_job_seeker(): void
    {
        $id = $this->applyTo();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        foreach ([['GET', ''], ['GET', "/{$id}"], ['GET', "/{$id}/resume"], ['POST', "/{$id}/withdraw"]] as [$method, $uri]) {
            $this->json($method, '/api/v1/me/applications'.$uri)->assertStatus(401);
        }

        foreach ([['GET', ''], ['GET', "/{$id}"], ['GET', "/{$id}/resume"], ['POST', "/{$id}/withdraw"]] as [$method, $uri]) {
            $this->mine($method, $uri, token: $this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        }
    }

    public function test_another_persons_application_does_not_exist(): void
    {
        $id = $this->applyTo();
        $other = $this->tokenFor($this->seeker('other@example.test'));

        $this->mine('GET', "/{$id}", token: $other)->assertStatus(404);
        $this->mine('GET', "/{$id}/resume", token: $other)->assertStatus(404);
        $this->mine('POST', "/{$id}/withdraw", token: $other)->assertStatus(404);
        $this->mine('GET', '', token: $other)->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $id)->value('stage_type'));
    }

    // ----- the list -----

    public function test_the_list_shows_the_newest_first_with_the_job_and_the_company(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        $first = $this->applyTo('First job');
        Carbon::setTestNow('2026-10-05 10:00:00');
        $second = $this->applyTo('Second job');

        $response = $this->mine('GET', '')->assertOk();

        $this->assertSame([$second, $first], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.job.title', 'Second job')
            ->assertJsonPath('data.0.company.name', DB::table('company_companies')->where('id', $this->companyId)->value('name'))
            ->assertJsonPath('data.0.company.id', $this->companyId)
            ->assertJsonPath('data.0.status', 'applied')
            ->assertJsonPath('data.0.applied_at', '2026-10-05T10:00:00+00:00')
            ->assertJsonPath('links.next', null);
        $this->assertSame(['id', 'job', 'company', 'status', 'applied_at', 'viewed_at'], array_keys($response->json('data.0')));
    }

    public function test_the_list_is_cursor_paginated_and_does_not_repeat_or_skip(): void
    {
        $ids = [];

        foreach (['A', 'B', 'C'] as $title) {
            Carbon::setTestNow(now()->addMinute());
            $ids[] = $this->applyTo("Job {$title}");
        }

        $first = $this->mine('GET', '?per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$ids[2], $ids[1]], array_column($first->json('data'), 'id'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        // A newer application arrives between the two requests: the next slice neither repeats nor skips.
        Carbon::setTestNow(now()->addMinute());
        $this->applyTo('Job D');

        $second = $this->mine('GET', '?per_page=2&cursor='.$cursor)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([$ids[0]], array_column($second->json('data'), 'id'));
        $this->assertNull($second->json('meta.next_cursor'));

        $this->mine('GET', '?cursor=garbage')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-cursor');
    }

    public function test_the_list_filters_by_stage_type_and_refuses_other_filters(): void
    {
        $kept = $this->applyTo('Kept job');
        $withdrawn = $this->applyTo('Withdrawn job');
        $this->mine('POST', "/{$withdrawn}/withdraw")->assertNoContent();

        $this->assertSame([$kept], array_column($this->mine('GET', '?filter[status]=applied')->assertOk()->json('data'), 'id'));
        $this->assertSame([$withdrawn], array_column($this->mine('GET', '?filter[status]=withdrawn')->assertOk()->json('data'), 'id'));
        $this->mine('GET', '?filter[status]=interview')->assertOk()->assertJsonCount(0, 'data');
        $this->mine('GET', '?filter[status]=unknown')->assertStatus(422);
        $this->mine('GET', '?filter[company]=1')->assertStatus(422);
    }

    // ----- the detail -----

    public function test_the_detail_shows_the_application_the_history_and_the_answers_but_nothing_internal(): void
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31']);
        $relocate = $this->question('yes_no', 'Can you relocate?');
        $tools = $this->question('multiple_choice', 'Which tools?', ['Git', 'Docker']);
        $this->setQuestions($job, [['question_id' => $relocate, 'must_have' => true, 'ideal' => ['yes' => true]], ['question_id' => $tools]]);
        $docker = $this->optionId($tools, 'Docker');

        $id = (int) $this->apply($job, ['cover_letter' => 'Hello', 'answers' => [
            ['question_id' => $relocate, 'value' => false],
            ['question_id' => $tools, 'value' => [$docker]],
        ]])->assertCreated()->json('data.id');

        $response = $this->mine('GET', "/{$id}")->assertOk();
        $response->assertJsonPath('data.job.title', 'Backend Developer')
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.cover_letter', 'Hello')
            ->assertJsonPath('data.resume.name', 'Siti CV.pdf')
            ->assertJsonPath('data.withdrawn_note', null)
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('data.history.0.status', 'applied')
            ->assertJsonPath('data.history.0.by', 'applicant')
            ->assertJsonCount(2, 'data.answers')
            ->assertJsonPath('data.answers.0.question', ['id' => $relocate, 'text' => 'Can you relocate?', 'type' => 'yes_no'])
            ->assertJsonPath('data.answers.0.bool', false)
            ->assertJsonPath('data.answers.1.options', [['id' => $docker, 'label' => 'Docker']]);

        $body = json_encode($response->json());
        $this->assertStringNotContainsString('meets_must_have', (string) $body);
        $this->assertStringNotContainsString('snapshot', (string) $body);
        $response->assertJsonPath('data.rejection_reason', null);
    }

    public function test_an_unknown_application_is_not_found(): void
    {
        $this->mine('GET', '/999999')->assertStatus(404);
        $this->mine('POST', '/999999/withdraw')->assertStatus(404);
    }

    // ----- the resume copy -----

    public function test_the_applicant_can_download_the_resume_copy_even_after_deleting_the_original(): void
    {
        $id = $this->applyTo();
        $this->api('DELETE', "/me/resumes/{$this->resumeId}", token: $this->seekerToken)->assertNoContent();

        $response = $this->mine('GET', "/{$id}/resume")->assertOk();

        $this->assertSame($this->pdf(), $response->getContent());
        $response->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "default-src 'none'");
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Siti CV.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_missing_resume_copy_is_not_found(): void
    {
        $id = $this->applyTo();
        Storage::delete((string) DB::table('recruitment_applications')->where('id', $id)->value('resume_path'));

        $this->mine('GET', "/{$id}/resume")->assertStatus(404);

        DB::table('recruitment_applications')->where('id', $id)->update(['resume_path' => null, 'resume_name' => null]);
        $this->mine('GET', "/{$id}/resume")->assertStatus(404);
        $this->mine('GET', "/{$id}")->assertOk()->assertJsonPath('data.resume', null);
    }

    // ----- withdrawal -----

    public function test_withdrawing_moves_the_application_to_the_withdrawn_stage_and_records_it(): void
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31']);
        $id = (int) $this->apply($job)->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-05 12:00:00');
        $this->mine('POST', "/{$id}/withdraw", ['note' => '  Found another job  '])->assertNoContent();

        $row = DB::table('recruitment_applications')->where('id', $id)->first();
        $withdrawnStage = DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'withdrawn')->where('is_default', 1)->value('id');

        $this->assertSame('withdrawn', $row->stage_type);
        $this->assertSame((int) $withdrawnStage, (int) $row->stage_id);
        $this->assertNull($row->active_key);
        $this->assertSame('Found another job', $row->withdrawn_note);
        $this->assertSame('2026-10-05 12:00:00', $row->updated_at);

        $history = DB::table('recruitment_application_stage_history')->where('application_id', $id)->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('applied', $history[1]->from_type);
        $this->assertSame('withdrawn', $history[1]->to_type);
        $this->assertSame('applicant', $history[1]->actor_kind);
        $this->assertSame($this->seekerId, (int) $history[1]->actor_id);

        $detail = $this->mine('GET', "/{$id}")->assertOk();
        $detail->assertJsonPath('data.status', 'withdrawn')->assertJsonPath('data.withdrawn_note', 'Found another job')->assertJsonCount(2, 'data.history');
        $this->assertSame(['applied', 'withdrawn'], array_column($detail->json('data.history'), 'status'));

        // The copy of the resume stays: the application is history, not deleted.
        $this->mine('GET', "/{$id}/resume")->assertOk();
    }

    public function test_a_withdrawal_needs_no_note_and_a_note_is_limited(): void
    {
        $this->mine('POST', '/'.$this->applyTo('One').'/withdraw')->assertNoContent();
        $this->assertNull(DB::table('recruitment_applications')->value('withdrawn_note'));

        $two = $this->applyTo('Two');
        $this->mine('POST', "/{$two}/withdraw", ['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-withdrawal')->assertJsonStructure(['errors' => ['note']]);
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $two)->value('stage_type'));
    }

    public function test_every_active_stage_except_hired_can_be_withdrawn(): void
    {
        foreach (['screening', 'interview', 'offer'] as $type) {
            $id = $this->applyTo('Job '.$type);
            DB::table('recruitment_applications')->where('id', $id)->update(['stage_type' => $type]);

            $this->mine('POST', "/{$id}/withdraw")->assertNoContent();
            $this->assertSame('withdrawn', DB::table('recruitment_applications')->where('id', $id)->value('stage_type'));
            $this->assertSame($type, DB::table('recruitment_application_stage_history')->where('application_id', $id)->orderByDesc('id')->value('from_type'));
        }
    }

    public function test_a_hired_rejected_or_withdrawn_application_cannot_be_withdrawn(): void
    {
        foreach (['hired', 'rejected', 'withdrawn'] as $type) {
            $id = $this->applyTo('Job '.$type);
            DB::table('recruitment_applications')->where('id', $id)->update(['stage_type' => $type]);

            $this->mine('POST', "/{$id}/withdraw")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:application-not-withdrawable');
            $this->assertSame($type, DB::table('recruitment_applications')->where('id', $id)->value('stage_type'));
            $this->assertSame(1, DB::table('recruitment_application_stage_history')->where('application_id', $id)->count());
        }
    }

    public function test_after_withdrawing_the_job_can_be_applied_to_again_but_not_after_a_rejection(): void
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31']);
        $first = (int) $this->apply($job)->assertCreated()->json('data.id');

        $this->apply($job)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-applied');

        $this->mine('POST', "/{$first}/withdraw")->assertNoContent();
        $second = (int) $this->apply($job)->assertCreated()->json('data.id');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, DB::table('recruitment_applications')->where('user_id', $this->seekerId)->where('job_id', $job)->count());
        $this->assertSame('withdrawn', DB::table('recruitment_applications')->where('id', $first)->value('stage_type'));
        $this->assertSame([$second, $first], array_column($this->mine('GET', '')->json('data'), 'id'));

        // A rejection is final: the key stays and the job cannot be applied to again.
        $stage = DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'rejected')->where('is_default', 1)->value('id');
        DB::table('recruitment_applications')->where('id', $second)->update(['stage_type' => 'rejected', 'stage_id' => $stage]);
        $this->apply($job)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-applied');
    }
}
