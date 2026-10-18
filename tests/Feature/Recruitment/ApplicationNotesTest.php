<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\EraseApplicantData;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ApplicationNotesTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function note(string $body, ?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/notes", ['body' => $body], $token ?? $this->recruiterToken);
    }

    private function rate(int $stars, ?string $token = null): TestResponse
    {
        return $this->api('PUT', "/company/applications/{$this->applicationId}/rating", ['stars' => $stars], $token ?? $this->recruiterToken);
    }

    private function reject(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();
    }

    // ----- access -----

    public function test_only_the_members_who_may_see_the_job_can_use_notes_and_ratings(): void
    {
        $id = $this->note('First look')->assertCreated()->json('data.id');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson("/api/v1/company/applications/{$this->applicationId}/notes")->assertStatus(401);

        $this->api('GET', "/company/applications/{$this->applicationId}/notes", token: $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->note('x', $this->seekerToken)->assertStatus(403);
        $this->rate(3, $this->seekerToken)->assertStatus(403);

        foreach ([$this->secondRecruiterToken, $this->otherAdminToken] as $token) {
            $this->api('GET', "/company/applications/{$this->applicationId}/notes", token: $token)->assertStatus(404);
            $this->note('x', $token)->assertStatus(404);
            $this->api('PUT', "/company/applications/{$this->applicationId}/notes/{$id}", ['body' => 'x'], $token)->assertStatus(404);
            $this->api('DELETE', "/company/applications/{$this->applicationId}/notes/{$id}", token: $token)->assertStatus(404);
            $this->rate(3, $token)->assertStatus(404);
            $this->api('DELETE', "/company/applications/{$this->applicationId}/rating", token: $token)->assertStatus(404);
        }

        $this->assertSame(1, DB::table('recruitment_application_notes')->count());
        $this->assertSame(0, DB::table('recruitment_application_ratings')->count());
    }

    // ----- notes -----

    public function test_a_member_writes_and_lists_notes_with_their_authors(): void
    {
        $this->note('  Strong profile.  ')->assertCreated()
            ->assertJsonPath('data.body', 'Strong profile.')
            ->assertJsonPath('data.author.id', $this->recruiterId)
            ->assertJsonPath('data.author.name', 'Rina Rekruter')
            ->assertJsonPath('data.edited_at', null);
        $this->note('Call on Monday.', $this->adminToken)->assertCreated();

        $this->api('GET', "/company/applications/{$this->applicationId}/notes", token: $this->recruiterToken)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Strong profile.')
            ->assertJsonPath('data.1.author.name', 'Andi Admin');

        $this->api('GET', "/company/applications/{$this->applicationId}/notes", token: $this->seekerToken)->assertStatus(403);
        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertOk()->assertJsonMissingPath('data.notes');
    }

    public function test_a_note_must_have_text_of_at_most_2000_characters(): void
    {
        $this->note('')->assertStatus(422);
        $this->note('   ')->assertStatus(422);
        $this->note(str_repeat('a', 2001))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-note');
        $this->note(str_repeat('é', 2000))->assertCreated();

        $this->assertSame(1, DB::table('recruitment_application_notes')->count());
    }

    public function test_only_the_author_edits_a_note_and_the_edit_is_marked(): void
    {
        $id = $this->note('Draft')->assertCreated()->json('data.id');

        $this->api('PUT', "/company/applications/{$this->applicationId}/notes/{$id}", ['body' => 'Hijacked'], $this->adminToken)
            ->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-note-author');

        $this->api('PUT', "/company/applications/{$this->applicationId}/notes/{$id}", ['body' => 'Draft'], $this->recruiterToken)->assertOk()->assertJsonPath('data.edited_at', null);
        $this->api('PUT', "/company/applications/{$this->applicationId}/notes/{$id}", ['body' => 'Final text'], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.body', 'Final text')
            ->assertJsonPath('data.edited_at', '2026-10-05T09:00:00+00:00');
        $this->api('PUT', "/company/applications/{$this->applicationId}/notes/{$id}", ['body' => ' '], $this->recruiterToken)->assertStatus(422);

        $this->assertSame('Final text', DB::table('recruitment_application_notes')->where('id', $id)->value('body'));
    }

    public function test_the_author_or_a_company_admin_deletes_a_note(): void
    {
        $first = $this->note('One')->assertCreated()->json('data.id');
        $second = $this->note('Two', $this->adminToken)->assertCreated()->json('data.id');

        $this->api('DELETE', "/company/applications/{$this->applicationId}/notes/{$second}", token: $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-note-author');
        $this->api('DELETE', "/company/applications/{$this->applicationId}/notes/{$first}", token: $this->adminToken)->assertNoContent();
        $this->api('DELETE', "/company/applications/{$this->applicationId}/notes/{$second}", token: $this->adminToken)->assertNoContent();
        $this->api('DELETE', "/company/applications/{$this->applicationId}/notes/{$second}", token: $this->adminToken)->assertStatus(404);

        $this->assertSame(0, DB::table('recruitment_application_notes')->count());
    }

    public function test_a_note_of_another_application_is_not_found_through_this_one(): void
    {
        $id = $this->note('Mine')->assertCreated()->json('data.id');
        $other = (int) $this->apply($this->insertJob('Designer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']), token: $this->seekerToken)->assertCreated()->json('data.id');

        $this->api('PUT', "/company/applications/{$other}/notes/{$id}", ['body' => 'x'], $this->recruiterToken)->assertStatus(404);
        $this->api('DELETE', "/company/applications/{$other}/notes/{$id}", token: $this->recruiterToken)->assertStatus(404);
    }

    public function test_notes_can_be_written_on_a_final_application(): void
    {
        $this->reject();

        $this->note('Rejected for budget reasons.')->assertCreated();
    }

    // ----- ratings -----

    public function test_a_member_rates_changes_and_withdraws_their_rating(): void
    {
        $this->rate(4)->assertOk()->assertJsonPath('data.average', 4)->assertJsonPath('data.count', 1)->assertJsonPath('data.mine', 4);
        $this->rate(5, $this->adminToken)->assertOk()->assertJsonPath('data.average', 4.5)->assertJsonPath('data.count', 2)->assertJsonPath('data.mine', 5);
        $this->rate(2)->assertOk()->assertJsonPath('data.average', 3.5)->assertJsonPath('data.mine', 2);

        $this->assertSame(2, DB::table('recruitment_application_ratings')->count());

        $this->api('DELETE', "/company/applications/{$this->applicationId}/rating", token: $this->adminToken)->assertOk()
            ->assertJsonPath('data.average', 2)->assertJsonPath('data.count', 1)->assertJsonPath('data.mine', null);
        $this->api('DELETE', "/company/applications/{$this->applicationId}/rating", token: $this->adminToken)->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_the_stars_must_be_from_one_to_five(): void
    {
        foreach ([0, 6, -1] as $stars) {
            $this->rate($stars)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-rating');
        }

        $this->api('PUT', "/company/applications/{$this->applicationId}/rating", ['stars' => 'many'], $this->recruiterToken)->assertStatus(422);
        $this->api('PUT', "/company/applications/{$this->applicationId}/rating", [], $this->recruiterToken)->assertStatus(422);

        $this->assertSame(0, DB::table('recruitment_application_ratings')->count());
    }

    public function test_ratings_are_locked_once_the_application_is_final(): void
    {
        $this->rate(4)->assertOk();
        $this->reject();

        $this->rate(1)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');
        $this->api('DELETE', "/company/applications/{$this->applicationId}/rating", token: $this->recruiterToken)->assertStatus(409);

        $this->assertSame(4, (int) DB::table('recruitment_application_ratings')->value('stars'));
    }

    public function test_ratings_are_locked_on_a_hired_application(): void
    {
        $hired = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'hired')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $hired, 'hire' => ['join_date' => now()->addDays(30)->format('Y-m-d')]], $this->recruiterToken)->assertOk();

        $this->rate(5)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');
        $this->note('Welcome aboard.')->assertCreated();
    }

    public function test_the_detail_and_the_list_show_the_ratings(): void
    {
        $this->rate(5)->assertOk();
        $this->rate(2, $this->adminToken)->assertOk();

        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.rating.average', 3.5)
            ->assertJsonPath('data.rating.count', 2)
            ->assertJsonPath('data.rating.mine', 5)
            ->assertJsonCount(2, 'data.rating.members')
            ->assertJsonPath('data.rating.members.1.name', 'Andi Admin')
            ->assertJsonPath('data.rating.members.1.stars', 2);

        $jobId = (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('job_id');
        $otherToken = $this->tokenFor($this->seeker('second@example.test', name: 'Budi Santoso'));
        $resumeId = $this->completeProfile($otherToken);
        $unrated = (int) $this->api('POST', "/jobs/{$jobId}/applications", ['resume_id' => $resumeId], $otherToken)->assertCreated()->json('data.id');

        $byId = collect($this->api('GET', "/company/jobs/{$jobId}/applications", token: $this->recruiterToken)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(['average' => 3.5, 'count' => 2], $byId[$this->applicationId]['rating']);
        $this->assertSame(['average' => null, 'count' => 0], $byId[$unrated]['rating']);

        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertOk()->assertJsonMissingPath('data.rating');
    }

    // ----- erasure -----

    public function test_anonymising_the_applicant_deletes_the_notes_and_keeps_the_ratings(): void
    {
        $this->note('She quoted her salary.')->assertCreated();
        $this->rate(4)->assertOk();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertSame(0, DB::table('recruitment_application_notes')->count());
        $this->assertSame(1, DB::table('recruitment_application_ratings')->count());
    }
}
