<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Assessment\Application\EraseAssessmentData;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\Concerns\RecordsVideoAnswers;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 2, part 3: the team streams a recorded answer, the assessor scores it, the files are purged when the retention period is over
 * (and the chunks of takes left unfinished), and an erased account loses its videos. The clock is frozen at 09:00.
 */
final class VideoReviewTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;
    use RecordsVideoAnswers;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
        $this->freeze(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function team(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->company($method, "invitations/{$this->invitationId}/{$uri}", $body, $token ?? $this->adminToken);
    }

    private function stream(?string $range = null, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->call('GET', "/api/v1/company/assessment/invitations/{$this->invitationId}/videos/{$this->questionId}", [], [], [], array_filter([
            'HTTP_AUTHORIZATION' => 'Bearer '.($token ?? $this->recruiterToken), 'HTTP_ACCEPT' => 'application/json', 'HTTP_RANGE' => $range,
        ]));
    }

    private function recordAndSubmit(): int
    {
        $this->startVideoAttempt();
        $takeId = $this->record();
        $this->take('POST', 'attempt/submit')->assertOk();

        return $takeId;
    }

    // ----- streaming -----

    public function test_the_team_streams_the_video_with_range_support(): void
    {
        $this->recordAndSubmit();
        $body = $this->webm('one-').'two';

        $whole = $this->stream()->assertOk();
        $this->assertSame($body, $whole->streamedContent());
        $whole->assertHeader('Content-Type', 'video/webm')->assertHeader('Accept-Ranges', 'bytes')->assertHeader('Content-Length', (string) strlen($body));
        $this->assertStringNotContainsString('attachment', (string) $whole->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $whole->headers->get('Cache-Control'));

        $part = $this->stream('bytes=4-7')->assertStatus(206)->assertHeader('Content-Range', 'bytes 4-7/'.strlen($body));
        $this->assertSame(substr($body, 4, 4), $part->streamedContent());
        $this->stream('bytes=500-')->assertStatus(416);
    }

    public function test_only_the_team_of_the_application_streams_a_submitted_video(): void
    {
        $this->startVideoAttempt();
        $this->record();

        $this->stream()->assertNotFound();

        $this->take('POST', 'attempt/submit')->assertOk();

        $this->stream(token: $this->adminToken)->assertOk();
        $this->stream(token: $this->otherAdminToken)->assertNotFound();
        $this->stream(token: $this->seekerToken)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken('')->getJson("/api/v1/company/assessment/invitations/{$this->invitationId}/videos/{$this->questionId}")->assertUnauthorized();
        $this->stream()->assertOk();
    }

    public function test_a_question_without_a_video_has_nothing_to_stream(): void
    {
        $this->startVideoAttempt();
        $this->take('POST', 'attempt/submit')->assertOk();

        $this->stream()->assertNotFound();
    }

    // ----- scoring -----

    public function test_an_assessor_scores_a_video_answer_and_the_result_becomes_final(): void
    {
        $this->recordAndSubmit();

        $this->team('GET', 'result')->assertOk()->assertJsonPath('data.result.status', 'pending_scoring')->assertJsonPath('data.result.percentage', null);
        $this->team('GET', 'scoring')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question_id', $this->questionId)->assertJsonPath('data.0.type', 'video_answer')->assertJsonPath('data.0.points', 10)
            ->assertJsonPath('data.0.answer', null)->assertJsonPath('data.0.score', null);

        $this->team('PUT', "scoring/questions/{$this->questionId}", ['score' => 11])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-score');
        $this->team('PUT', "scoring/questions/{$this->questionId}", ['score' => 8])->assertOk()->assertJsonPath('data.type', 'video_answer')->assertJsonPath('data.score', 8);

        $result = $this->team('GET', 'result')->assertOk()->assertJsonPath('data.result.status', 'scored');
        $this->assertEquals([8, 80], [$result->json('data.result.earned_points'), $result->json('data.result.percentage')]);
        $this->assertSame($this->adminId, (int) DB::table('assessment_answers')->where('question_id', $this->questionId)->value('scored_by_user_id'));
    }

    public function test_a_video_question_without_a_take_counts_zero_and_waits_for_no_one(): void
    {
        $this->startVideoAttempt();
        $this->take('POST', 'attempt/submit')->assertOk();

        $result = $this->team('GET', 'result')->assertOk()->assertJsonPath('data.result.status', 'scored');
        $this->assertEquals(0, $result->json('data.result.percentage'));
        $this->team('GET', 'scoring')->assertOk()->assertJsonCount(0, 'data');
    }

    // ----- retention -----

    public function test_the_files_are_purged_after_the_retention_period_and_the_score_stays(): void
    {
        $takeId = $this->recordAndSubmit();
        $path = (string) DB::table('assessment_video_takes')->where('id', $takeId)->value('path');
        $this->team('PUT', "scoring/questions/{$this->questionId}", ['score' => 6])->assertOk();

        $this->assertSame('2027-01-03', substr((string) DB::table('assessment_video_takes')->where('id', $takeId)->value('expires_at'), 0, 10));

        $this->freeze(89 * 86400);
        Artisan::call('assessment:purge-expired-videos');
        Storage::assertExists($path);

        $this->freeze(91 * 86400);
        $this->assertSame(0, Artisan::call('assessment:purge-expired-videos'));
        Storage::assertMissing($path);

        $take = DB::table('assessment_video_takes')->where('id', $takeId)->first();
        $this->assertSame('purged', $take->status);
        $this->assertNull($take->path);
        $this->assertSame($takeId, (int) DB::table('assessment_answers')->where('question_id', $this->questionId)->value('video_take_id'));
        $this->assertEquals(6, DB::table('assessment_answers')->where('question_id', $this->questionId)->value('score'));

        // The access tokens of the test accounts do not live that long, so the team looks again with the clock back.
        $this->freeze(0);
        $this->stream()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-expired');
    }

    public function test_the_retention_period_is_a_setting(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('assessment.video_retention_days', 30, null);

        $takeId = $this->recordAndSubmit();

        $this->assertSame('2026-11-04', substr((string) DB::table('assessment_video_takes')->where('id', $takeId)->value('expires_at'), 0, 10));
    }

    public function test_the_chunks_of_a_take_left_unfinished_for_a_day_are_deleted(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm());
        $this->assertCount(1, Storage::allFiles('assessment/video-chunks'));

        $this->freeze(23 * 3600);
        Artisan::call('assessment:purge-stale-video-chunks');
        $this->assertCount(1, Storage::allFiles('assessment/video-chunks'));

        $this->freeze(25 * 3600);
        $this->assertSame(0, Artisan::call('assessment:purge-stale-video-chunks'));
        $this->assertSame([], Storage::allFiles('assessment/video-chunks'));
        $this->assertSame('rejected', DB::table('assessment_video_takes')->where('id', $takeId)->value('status'));
    }

    // ----- erasure -----

    public function test_erasing_an_account_deletes_its_videos_and_takes(): void
    {
        $this->startVideoAttempt();
        $this->record();
        $abandoned = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($abandoned, 0, $this->webm());
        $this->assertNotSame([], Storage::allFiles('assessment'));

        $this->app->make(EraseAssessmentData::class)->execute($this->seekerId);

        $this->assertSame([], Storage::allFiles('assessment'));
        $this->assertSame(0, DB::table('assessment_video_takes')->count());
        $this->assertSame(0, DB::table('assessment_answers')->count());
    }
}
