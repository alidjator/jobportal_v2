<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\Concerns\RecordsVideoAnswers;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 2, part 2: recording the answer to a video question. A take is sent in chunks, in order, joined when it is complete and
 * becomes the answer; retakes are counted and the sizes, the type and the upload deadline are checked on the server. The clock is frozen at 09:00.
 */
final class VideoTakesTest extends FeatureTestCase
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

        $this->startTake()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:retake-limit');
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
        $this->startTake()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:retake-limit');
    }

    // ----- what the server checks -----

    public function test_the_chunks_must_come_in_order_and_a_resent_chunk_is_ignored(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->sendChunkRaw($takeId, 1, 'two')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:chunk-out-of-order')->assertJsonPath('expected_index', 0);
        $this->sendChunk($takeId, 0, $this->webm());
        $this->sendChunk($takeId, 0, $this->webm())->assertOk()->assertJsonPath('data.chunks_received', 1);
        $this->assertSame(1, (int) DB::table('assessment_video_takes')->where('id', $takeId)->value('chunk_count'));
    }

    public function test_the_type_is_read_from_the_content_not_from_the_browser(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->sendChunkRaw($takeId, 0, '<?php echo 1;')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-video')->assertJsonStructure(['errors' => ['file']]);
        $this->sendChunkRaw($takeId, 0, '')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-video');
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

        $this->sendChunkRaw($takeId, 0, $this->webm(str_repeat('x', 5 * 1024 * 1024)))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large');

        $this->app->make(SettingsStore::class)->setGlobal('assessment.max_video_megabytes', 5, null);
        $this->sendChunk($takeId, 0, $this->webm(str_repeat('x', 4 * 1024 * 1024)));
        $this->sendChunkRaw($takeId, 1, str_repeat('y', 2 * 1024 * 1024))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-too-large')->assertJsonPath('max_size_mb', 5);
    }

    public function test_the_upload_deadline_is_the_answer_time_plus_two_minutes(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm());

        $this->freeze(60 + 120);
        $this->sendChunk($takeId, 1, 'late-but-in-grace')->assertOk();

        $this->freeze(60 + 121);
        $this->sendChunkRaw($takeId, 2, 'too-late')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:upload-deadline-passed');
        $this->completeTake($takeId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:upload-deadline-passed');
        $this->take('GET', $this->videoUri())->assertOk()->assertJsonPath('data.recording', null);
    }

    public function test_a_take_that_is_complete_or_has_no_chunk_cannot_be_completed(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');

        $this->completeTake($takeId)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-video');

        $this->sendChunk($takeId, 0, $this->webm());
        $this->completeTake($takeId)->assertOk();
        $this->completeTake($takeId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-take-closed');
        $this->sendChunkRaw($takeId, 1, 'more')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-take-closed');
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

        $this->take('PUT', "attempt/questions/{$this->questionId}/answer", ['text' => 'typed'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
        $this->take('DELETE', "attempt/questions/{$this->questionId}/answer")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
    }

    public function test_nothing_is_recorded_once_the_attempt_is_closed(): void
    {
        $this->startVideoAttempt();
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm());

        $this->take('POST', 'attempt/submit')->assertOk();

        $this->completeTake($takeId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');
        $this->startTake()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');
    }
}
