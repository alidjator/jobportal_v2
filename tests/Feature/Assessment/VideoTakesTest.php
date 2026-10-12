<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 2, part 2: recording the answer to a video question. A take is sent in chunks, in order, joined when it is complete and
 * becomes the answer; retakes are counted and the sizes, the type and the upload deadline are checked on the server. The clock is frozen at 09:00.
 */
final class VideoTakesTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $applicationId;

    private int $invitationId = 0;

    private int $questionId = 0;

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

    // ----- helpers -----

    private function freeze(int $seconds): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00')->addSeconds($seconds));
    }

    /** A published test with one video section (1200 s) and one video question: answer 60 s, `$retakes` retakes. It is sent and started. */
    private function startVideoAttempt(int $retakes = 1, int $answerSeconds = 60): void
    {
        $id = $this->newTest('company', ['title' => 'Video screening']);
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", ['title' => 'Interview', 'format' => 'video', 'duration_seconds' => 1200])->assertCreated()->json('data.id');
        $this->questionId = (int) $this->company('POST', "sections/{$sectionId}/questions", [
            'type' => 'video_answer', 'text' => 'Tell us about a project.', 'points' => 10, 'think_seconds' => null, 'answer_seconds' => $answerSeconds, 'max_retakes' => $retakes,
        ])->assertCreated()->json('data.id');
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", [
            'stage_id' => $stage,
            'assessment' => ['test_id' => $id, 'due_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z'), 'assessor_ids' => [$this->adminId]],
        ], $this->recruiterToken)->assertNoContent();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');

        $this->take('POST', 'start', ['consent' => true, 'video_consent' => true])->assertCreated();
    }

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $token ?? $this->seekerToken);
    }

    private function videoUri(string $suffix = ''): string
    {
        return "attempt/questions/{$this->questionId}/video{$suffix}";
    }

    private function startTake(): TestResponse
    {
        return $this->take('POST', $this->videoUri('/takes'));
    }

    /** Sends a chunk that the server accepts. */
    private function sendChunk(int $takeId, int $index, string $contents): TestResponse
    {
        return $this->sendChunkRaw($takeId, $index, $contents)->assertOk();
    }

    private function webm(string $body = 'frames'): string
    {
        return "\x1A\x45\xDF\xA3".$body;
    }

    private function completeTake(int $takeId): TestResponse
    {
        return $this->take('POST', $this->videoUri("/takes/{$takeId}/complete"));
    }

    /** Records a whole take of two chunks and completes it. */
    private function record(string $first = 'one-'): int
    {
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm($first));
        $this->sendChunk($takeId, 1, 'two');
        $this->completeTake($takeId)->assertOk();

        return $takeId;
    }

    // ----- the recording -----

    public function test_a_take_is_sent_in_chunks_joined_and_becomes_the_answer(): void
    {
        $this->startVideoAttempt();

        $this->take('GET', $this->videoUri())->assertOk()->assertJsonPath('data.takes_used', 0)->assertJsonPath('data.takes_allowed', 2)->assertJsonPath('data.answer', null)->assertJsonPath('data.recording', null);

        $take = $this->startTake()->assertCreated()->assertJsonPath('data.number', 1)->assertJsonPath('data.status', 'recording')->assertJsonPath('data.deadline_at', '2026-10-05T09:03:00+00:00');
        $takeId = (int) $take->json('data.id');

        $this->sendChunk($takeId, 0, $this->webm('one-'))->assertOk()->assertJsonPath('data.chunks_received', 1);
        $this->sendChunk($takeId, 1, 'two')->assertOk()->assertJsonPath('data.chunks_received', 2)->assertJsonPath('data.bytes_received', 11);
        $this->take('GET', $this->videoUri())->assertOk()->assertJsonPath('data.recording.id', $takeId)->assertJsonPath('data.answer', null);

        $this->completeTake($takeId)->assertOk()->assertJsonPath('data.status', 'complete');

        $row = DB::table('assessment_video_takes')->where('id', $takeId)->first();
        $this->assertSame('video/webm', $row->mime_type);
        $this->assertSame(11, (int) $row->size_bytes);
        $this->assertSame($this->webm('one-').'two', Storage::get($row->path));
        $this->assertStringEndsWith('.webm', $row->path);
        $this->assertSame([], Storage::allFiles('assessment/video-chunks'));
        $this->assertSame($takeId, (int) DB::table('assessment_answers')->where('question_id', $this->questionId)->value('video_take_id'));
        $this->take('GET', $this->videoUri())->assertOk()->assertJsonPath('data.answer.id', $takeId)->assertJsonPath('data.recording', null);
        $this->take('GET', 'attempt')->assertOk()->assertJsonPath('data.questions.0.answer.option_positions', []);
    }

    public function test_a_retake_replaces_the_answer_and_deletes_the_earlier_file(): void
    {
        $this->startVideoAttempt();
        $first = $this->record();
        $firstPath = (string) DB::table('assessment_video_takes')->where('id', $first)->value('path');

        $second = (int) $this->startTake()->assertCreated()->assertJsonPath('data.number', 2)->json('data.id');
        Storage::assertExists($firstPath);
        $this->sendChunk($second, 0, $this->webm('again'));
        $this->completeTake($second)->assertOk();

        Storage::assertMissing($firstPath);
        $this->assertSame(['rejected', 'complete'], DB::table('assessment_video_takes')->orderBy('number')->pluck('status')->all());
        $this->assertSame($second, (int) DB::table('assessment_answers')->where('question_id', $this->questionId)->value('video_take_id'));
        $this->assertSame(1, DB::table('assessment_answers')->count());
        $this->take('GET', $this->videoUri())->assertOk()->assertJsonPath('data.takes_used', 2)->assertJsonPath('data.answer.id', $second);
    }

    public function test_the_retakes_of_a_question_are_limited(): void
    {
        $this->startVideoAttempt(retakes: 0);
        $this->record();

        $this->startTake()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:retake-limit');
    }

    public function test_a_take_given_up_half_way_still_counts_and_loses_its_chunks(): void
    {
        $this->startVideoAttempt();
        $abandoned = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($abandoned, 0, $this->webm());
        $this->assertCount(1, Storage::allFiles('assessment/video-chunks'));

        $next = $this->startTake()->assertCreated()->assertJsonPath('data.number', 2);

        $this->assertSame([], Storage::allFiles('assessment/video-chunks'));
        $this->assertSame('rejected', DB::table('assessment_video_takes')->where('id', $abandoned)->value('status'));
        $this->sendChunk((int) $next->json('data.id'), 0, $this->webm());
        $this->startTake()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:retake-limit');
    }

    // ----- what the server checks -----

    public function test_the_chunks_must_come_in_order_and_a_resent_chunk_is_ignored(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->sendChunkRaw($takeId, 1, 'two')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:chunk-out-of-order')->assertJsonPath('expected_index', 0);
        $this->sendChunk($takeId, 0, $this->webm());
        $this->sendChunk($takeId, 0, $this->webm())->assertOk()->assertJsonPath('data.chunks_received', 1);
        $this->assertSame(1, (int) DB::table('assessment_video_takes')->where('id', $takeId)->value('chunk_count'));
    }

    public function test_the_type_is_read_from_the_content_not_from_the_browser(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->sendChunkRaw($takeId, 0, '<?php echo 1;')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-video')->assertJsonStructure(['errors' => ['file']]);
        $this->sendChunkRaw($takeId, 0, '')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-video');
        $this->assertSame([], Storage::allFiles('assessment'));

        $this->sendChunk($takeId, 0, "\x00\x00\x00\x18ftypmp42morebytes");
        $this->completeTake($takeId)->assertOk();
        $this->assertSame('video/mp4', DB::table('assessment_video_takes')->where('id', $takeId)->value('mime_type'));
        $this->assertStringEndsWith('.mp4', (string) DB::table('assessment_video_takes')->where('id', $takeId)->value('path'));
    }

    public function test_a_chunk_and_the_whole_recording_have_a_size_limit(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->sendChunkRaw($takeId, 0, $this->webm(str_repeat('x', 5 * 1024 * 1024)))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:file-too-large');

        $this->app->make(SettingsStore::class)->setGlobal('assessment.max_video_megabytes', 5, null);
        $this->sendChunk($takeId, 0, $this->webm(str_repeat('x', 4 * 1024 * 1024)));
        $this->sendChunkRaw($takeId, 1, str_repeat('y', 2 * 1024 * 1024))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:video-too-large')->assertJsonPath('max_size_mb', 5);
    }

    public function test_the_upload_deadline_is_the_answer_time_plus_two_minutes(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm());

        $this->freeze(60 + 120);
        $this->sendChunk($takeId, 1, 'late-but-in-grace')->assertOk();

        $this->freeze(60 + 121);
        $this->sendChunkRaw($takeId, 2, 'too-late')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:upload-deadline-passed');
        $this->completeTake($takeId)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:upload-deadline-passed');
        $this->take('GET', $this->videoUri())->assertOk()->assertJsonPath('data.recording', null);
    }

    public function test_a_take_that_is_complete_or_has_no_chunk_cannot_be_completed(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->completeTake($takeId)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-video');

        $this->sendChunk($takeId, 0, $this->webm());
        $this->completeTake($takeId)->assertOk();
        $this->completeTake($takeId)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:video-take-closed');
        $this->sendChunkRaw($takeId, 1, 'more')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:video-take-closed');
    }

    public function test_only_the_video_question_of_the_open_attempt_takes_recordings(): void
    {
        $this->startVideoAttempt();
        $takeId = $this->record();

        $this->take('POST', "attempt/questions/{$this->questionId}/video/takes", token: $this->recruiterToken)->assertForbidden();
        $this->sendChunkRaw($takeId, 2, 'x', $this->recruiterToken)->assertForbidden();

        $other = $this->questionId + 1000;
        $this->take('POST', "attempt/questions/{$other}/video/takes")->assertNotFound();
        $this->take('POST', $this->videoUri('/takes/999999/complete'))->assertNotFound();

        $this->take('PUT', "attempt/questions/{$this->questionId}/answer", ['text' => 'typed'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-answer');
        $this->take('DELETE', "attempt/questions/{$this->questionId}/answer")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-answer');
    }

    public function test_nothing_is_recorded_once_the_attempt_is_closed(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm());

        $this->take('POST', 'attempt/submit')->assertOk();

        $this->completeTake($takeId)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:attempt-closed');
        $this->startTake()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:attempt-closed');
    }

    /** Sends a chunk without asserting success. */
    private function sendChunkRaw(int $takeId, int $index, string $contents, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->call(
            'PUT',
            "/api/v1/me/assessment/invitations/{$this->invitationId}/".$this->videoUri("/takes/{$takeId}/chunks/{$index}"),
            [], [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.($token ?? $this->seekerToken), 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/octet-stream'],
            $contents,
        );
    }
}
