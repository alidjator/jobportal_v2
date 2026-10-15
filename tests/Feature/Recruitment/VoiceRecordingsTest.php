<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Src\Recruitment\Application\Voice\VoiceRecordingPipeline;
use Src\Recruitment\Domain\Events\VoiceRecordingStopped;
use Tests\Concerns\DrivesVoiceInterviews;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 3, part 3: the panel records the phone call (announced to everyone in the bridge), the recording ends with the call, and the file is
 * fetched from the voice server, stored on the private disk and streamed like a video recording. The voice server is faked.
 */
final class VoiceRecordingsTest extends FeatureTestCase
{
    use DrivesVoiceInterviews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpVoiceInterviews();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** A recorded phone interview the job seeker consented to, dialled and answered; returns the channel of the candidate. */
    private function answeredRecordedCall(string $consent = 'granted'): string
    {
        $this->confirmedVoiceInterview(['recorded' => true], confirm: ['phone' => '0812-3456-7890', 'recording_consent' => $consent]);
        $callId = $this->dial();
        $channel = $this->channelOf($callId);
        $this->hear(['type' => 'StasisStart', 'args' => ['candidate', (string) $callId], 'channel' => ['id' => $channel, 'name' => 'PJSIP/trunk-00000001', 'state' => 'Up']]);

        return $channel;
    }

    private function recording(string $action, ?string $token = null): TestResponse
    {
        return $this->team("recording/{$action}", $token);
    }

    // ----- starting and stopping -----

    public function test_the_panel_records_a_call_which_is_announced_stored_and_streamed(): void
    {
        Carbon::setTestNow(now()->startOfSecond());
        $this->answeredRecordedCall();
        $interviewId = $this->interviewId();

        $started = $this->recording('start')->assertCreated()->assertJsonPath('data.status', 'recording')->assertJsonPath('data.media', 'audio');
        $id = (int) $started->json('data.id');
        $name = "interview-recording-{$id}";

        $record = $this->ari('post', "/bridges/interview-{$interviewId}/record");
        $this->assertCount(1, $record);
        $this->assertSame(['name' => $name, 'format' => 'wav', 'maxDurationSeconds' => '3600', 'ifExists' => 'fail', 'beep' => 'false'], $this->queryOf($record[0]));
        $play = $this->ari('post', "/bridges/interview-{$interviewId}/play");
        $this->assertCount(1, $play);
        $this->assertSame(['media' => 'sound:jobportal/recording-announcement'], $this->queryOf($play[0]));

        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:recording-in-progress');

        Carbon::setTestNow(now()->addSeconds(125));
        $this->recording('stop')->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'processing');
        $this->assertCount(1, $this->ari('post', "/recordings/live/{$name}/stop"));

        // The queue runs at once in tests: the file is fetched, stored and removed from the voice server.
        $row = DB::table('recruitment_interview_recordings')->where('id', $id)->first();
        $this->assertSame('ready', $row->status);
        $this->assertSame('audio', $row->media);
        $this->assertSame(125, (int) $row->duration_seconds);
        $this->assertStringStartsWith("interview-recordings/{$interviewId}/", $row->path);
        $this->assertStringEndsWith('.wav', $row->path);
        Storage::assertExists($row->path);
        $this->assertSame('RIFF-wav-data', Storage::get($row->path));
        $this->assertSame(strlen('RIFF-wav-data'), (int) $row->size_bytes);
        $this->assertEqualsWithDelta(now()->addMinutes(5 + 60)->addDays(90)->getTimestamp(), Carbon::parse($row->expires_at)->getTimestamp(), 3600);
        $this->assertCount(1, $this->ari('delete', "/recordings/stored/{$name}"));

        $file = $this->api('GET', "/company/interviews/{$interviewId}/recordings/{$id}/file", [], $this->adminToken)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertSame('RIFF-wav-data', $file->streamedContent());
        $this->api('GET', "/company/interviews/{$interviewId}/recordings", [], $this->adminToken)->assertOk()->assertJsonPath('data.0.media', 'audio')->assertJsonPath('data.0.status', 'ready');
    }

    public function test_recording_needs_consent_a_panel_member_and_the_candidate_on_the_line(): void
    {
        $this->answeredRecordedCall('refused');
        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:recording-not-allowed');
        $this->assertSame(0, DB::table('recruitment_interview_recordings')->count());
    }

    public function test_an_interview_that_is_not_marked_as_recorded_cannot_be_recorded(): void
    {
        $this->confirmedVoiceInterview();
        $callId = $this->dial();
        $this->hear(['type' => 'StasisStart', 'args' => ['candidate', (string) $callId], 'channel' => ['id' => $this->channelOf($callId), 'name' => 'PJSIP/trunk-00000001']]);

        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:recording-not-allowed');
    }

    public function test_recording_starts_only_while_the_candidate_is_on_the_line(): void
    {
        $this->confirmedVoiceInterview(['recorded' => true], confirm: ['phone' => '0812-3456-7890', 'recording_consent' => 'granted']);
        $this->team('join')->assertOk();

        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-live-call');

        // Ringing is not enough: nobody would hear the announcement.
        $callId = (int) $this->team('call')->assertCreated()->json('data.id');
        $this->hear(['type' => 'ChannelStateChange', 'channel' => ['id' => $this->channelOf($callId), 'state' => 'Ringing']]);
        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-live-call');
        $this->assertSame(0, DB::table('recruitment_interview_recordings')->count());
    }

    public function test_only_the_panel_starts_and_stops_a_recording(): void
    {
        $this->answeredRecordedCall();

        $this->recording('start', $this->adminToken)->assertForbidden();
        $this->recording('start', $this->seekerToken)->assertForbidden();
        $this->recording('stop')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-recording');

        $this->recording('start')->assertCreated();
        $this->recording('stop', $this->adminToken)->assertForbidden();
        $this->recording('stop')->assertOk();
    }

    public function test_a_failure_to_announce_stops_the_recording_and_leaves_nothing_behind(): void
    {
        $this->answeredRecordedCall();
        $this->announceFails = true;

        $this->recording('start')->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:voice-server-unavailable');

        $this->assertSame(0, DB::table('recruitment_interview_recordings')->count());
        $this->assertCount(1, $this->ari('post', '/recordings/live/'));
        $this->assertCount(1, $this->ari('delete', '/recordings/stored/'));
    }

    public function test_withdrawing_the_consent_is_refused_after_a_call_recording_started(): void
    {
        $this->answeredRecordedCall();
        $this->recording('start')->assertCreated();

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/recording-consent/withdraw", [], $this->seekerToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:recording-started');
    }

    // ----- ending -----

    public function test_the_recording_ends_with_the_call(): void
    {
        $channel = $this->answeredRecordedCall();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');

        $this->hear(['type' => 'ChannelDestroyed', 'cause' => 16, 'channel' => ['id' => $channel]]);

        $this->assertCount(1, $this->ari('post', "/recordings/live/interview-recording-{$id}/stop"));
        $this->assertSame('ready', DB::table('recruitment_interview_recordings')->where('id', $id)->value('status'));
        $this->assertSame('ended', DB::table('recruitment_interview_calls')->value('status'));
    }

    public function test_a_recording_left_running_without_a_call_is_stopped_by_the_sweep(): void
    {
        $channel = $this->answeredRecordedCall();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');

        // The hangup event was lost: the call is closed in the log by hand.
        DB::table('recruitment_interview_calls')->where('channel_id', $channel)->update(['status' => 'ended', 'ended_at' => now()]);
        Artisan::call('recruitment:close-voice-sessions');

        $this->assertCount(1, $this->ari('post', "/recordings/live/interview-recording-{$id}/stop"));
        $this->assertSame('ready', DB::table('recruitment_interview_recordings')->where('id', $id)->value('status'));
    }

    public function test_a_recording_is_kept_when_the_voice_server_cannot_be_told_to_stop_and_stopped_later(): void
    {
        $channel = $this->answeredRecordedCall();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');

        $this->ariDown = true;
        $this->hear(['type' => 'ChannelDestroyed', 'cause' => 16, 'channel' => ['id' => $channel]]);
        $this->assertSame('ended', DB::table('recruitment_interview_calls')->value('status'));
        $this->assertSame('recording', DB::table('recruitment_interview_recordings')->where('id', $id)->value('status'));

        $this->ariDown = false;
        Artisan::call('recruitment:close-voice-sessions');
        $this->assertSame('ready', DB::table('recruitment_interview_recordings')->where('id', $id)->value('status'));
    }

    // ----- storing -----

    public function test_a_file_the_voice_server_does_not_have_yet_is_tried_again_and_a_lost_one_fails_the_recording(): void
    {
        Event::fake([VoiceRecordingStopped::class]);
        $this->answeredRecordedCall();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');
        $this->recording('stop')->assertOk();
        Event::assertDispatched(VoiceRecordingStopped::class, fn (VoiceRecordingStopped $event): bool => $event->recordingId === $id);

        $pipeline = $this->app->make(VoiceRecordingPipeline::class);
        $this->recordingFile = null;

        try {
            $pipeline->store($id);
            $this->fail('A missing file must make the queue try again.');
        } catch (RuntimeException) {
            $this->assertSame('processing', DB::table('recruitment_interview_recordings')->where('id', $id)->value('status'));
        }

        $this->recordingFile = 'RIFF-later';
        $pipeline->store($id);
        $this->assertSame('ready', DB::table('recruitment_interview_recordings')->where('id', $id)->value('status'));
        // Storing again changes nothing.
        $pipeline->store($id);
        $this->assertCount(1, $this->ari('delete', "/recordings/stored/interview-recording-{$id}"));
    }

    public function test_a_recording_that_cannot_be_stored_is_marked_failed_without_a_file(): void
    {
        Event::fake([VoiceRecordingStopped::class]);
        $this->answeredRecordedCall();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');
        $this->recording('stop')->assertOk();

        $this->app->make(VoiceRecordingPipeline::class)->fail($id);

        $row = DB::table('recruitment_interview_recordings')->where('id', $id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertNull($row->path);
        $this->api('GET', "/company/interviews/{$this->interviewId()}/recordings/{$id}/file", [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:recording-not-ready');
    }
}
