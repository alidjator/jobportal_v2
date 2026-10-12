<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\Video\CloseVideoRooms;
use Src\Recruitment\Application\Video\PurgeExpiredRecordings;
use Src\Recruitment\Application\Video\RecordingPipeline;
use Src\Recruitment\Domain\Events\RecordingConverted;
use Src\Recruitment\Domain\Events\RecordingStopped;
use Src\Recruitment\Infrastructure\Listeners\ConvertRecordingWhenStopped;
use Src\Recruitment\Infrastructure\Listeners\StoreRecordingWhenConverted;
use Src\Recruitment\Infrastructure\Video\RecordingChangedBroadcast;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** Form 1 part 3 of the tele-interview (`docs/design/phase-7-tele-interview.md`): recording, the converter webhook, retention, attendance and erasure. */
final class VideoRecordingsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    private string $volume;

    private bool $converterDown = false;

    private bool $janusDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
        $this->volume = storage_path('framework/testing/recordings-'.getmypid());
        File::deleteDirectory($this->volume);
        File::ensureDirectoryExists($this->volume.'/ready');

        config([
            'services.janus' => [
                'http_url' => 'http://janus.test:8088/janus', 'admin_url' => 'http://janus.test:7088/admin', 'admin_secret' => 'admin-secret',
                'api_token' => 'api-token', 'ws_url' => 'wss://video.example.test/janus', 'webhook_secret' => 'hook',
                'converter_url' => 'http://converter.test/convert', 'recordings_dir' => '/recordings', 'recordings_path' => $this->volume,
            ],
        ]);

        Http::fake([
            'janus.test:8088/*' => function (Request $request) {
                if ($this->janusDown) {
                    return Http::response('', 503);
                }

                $path = parse_url($request->url(), PHP_URL_PATH);

                $answer = match ($request->data()['body']['request'] ?? null) {
                    'create' => 'created',
                    'destroy' => 'destroyed',
                    default => 'edited',
                };

                return match (true) {
                    $path === '/janus' => Http::response(['janus' => 'success', 'data' => ['id' => 111]]),
                    $path === '/janus/111' && ($request->data()['janus'] ?? null) === 'attach' => Http::response(['janus' => 'success', 'data' => ['id' => 222]]),
                    $path === '/janus/111/222' => Http::response(['janus' => 'success', 'plugindata' => ['plugin' => 'janus.plugin.videoroom', 'data' => ['videoroom' => $answer]]]),
                    default => Http::response(['janus' => 'success']),
                };
            },
            'janus.test:7088/*' => Http::response(['janus' => 'success']),
            'converter.test/*' => fn () => $this->converterDown ? Http::response('', 503) : Http::response('', 202),
        ]);

        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VIDEO_ENABLED, true, null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->volume);
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    private function minutesFromNow(int $minutes): string
    {
        return now()->addMinutes($minutes)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** A recorded video interview that starts in five minutes, with the job seeker's consent. */
    private function consentedInterview(string $consent = 'granted'): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['format' => 'video', 'meeting_url' => null, 'starts_at' => $this->minutesFromNow(5), 'recorded' => true, 'participant_ids' => [$this->adminId]]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", ['recording_consent' => $consent], $this->seekerToken)->assertOk();
    }

    private function interviewId(): int
    {
        return (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('id');
    }

    private function join(?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/interview/video/join", [], $token ?? $this->recruiterToken);
    }

    private function recording(string $action, ?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/interview/video/recording/{$action}", [], $token ?? $this->recruiterToken);
    }

    /** @return list<array<string, mixed>> */
    private function sentToRoom(string $name): array
    {
        $bodies = [];

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), ':8088') && ($request->data()['body']['request'] ?? null) === $name) {
                $bodies[] = $request->data();
            }
        }

        return $bodies;
    }

    private function convertRequests(): int
    {
        return count(array_filter(Http::recorded()->all(), fn (array $pair): bool => str_contains($pair[0]->url(), 'converter.test')));
    }

    /** The recording is started and stopped in a room that is open; returns its id. */
    private function stoppedRecording(): int
    {
        $this->consentedInterview();
        $this->join()->assertOk();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');
        $this->recording('stop')->assertOk();

        return $id;
    }

    /** @param  array<string, mixed>  $body */
    private function webhook(string $path, array $body, ?string $secret = 'hook'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->json('POST', '/api/v1/internal/janus/'.$path, $body, $secret === null ? [] : ['Authorization' => 'Bearer '.$secret]);
    }

    private function converted(int $id, string $contents = 'webm-bytes'): void
    {
        File::put("{$this->volume}/ready/{$id}.webm", $contents);
        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'ready', 'file' => "ready/{$id}.webm", 'duration_seconds' => 1500])->assertNoContent();
    }

    // ----- starting and stopping -----

    public function test_a_panel_member_starts_and_stops_a_recording_of_a_consented_interview(): void
    {
        Event::fake([RecordingChangedBroadcast::class]);
        $this->consentedInterview();
        $this->join()->assertOk();

        $started = $this->recording('start')->assertCreated()->assertJsonPath('data.status', 'recording')->assertJsonPath('data.started_by', $this->recruiterId);
        $id = $started->json('data.id');

        $edit = $this->sentToRoom('edit');
        $this->assertSame("/recordings/raw/{$id}", $edit[0]['body']['new_rec_dir']);
        $this->assertTrue($this->sentToRoom('enable_recording')[0]['body']['record']);
        Event::assertDispatched(RecordingChangedBroadcast::class, fn (RecordingChangedBroadcast $event): bool => $event->recording && $event->interviewId === $this->interviewId());

        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recording-in-progress');

        $stopped = $this->recording('stop')->assertOk()->assertJsonPath('data.status', 'processing');
        $this->assertNotNull($stopped->json('data.stopped_at'));
        $this->assertFalse($this->sentToRoom('enable_recording')[1]['body']['record']);
        Event::assertDispatched(RecordingChangedBroadcast::class, fn (RecordingChangedBroadcast $event): bool => ! $event->recording);
        $this->assertSame(1, $this->convertRequests());

        $this->recording('stop')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:no-active-recording');
    }

    public function test_the_converter_is_asked_with_the_shared_secret_and_the_raw_directory(): void
    {
        $id = $this->stoppedRecording();

        $request = collect(Http::recorded()->all())->map(fn (array $pair): Request => $pair[0])->first(fn (Request $request): bool => str_contains($request->url(), 'converter.test'));
        $this->assertSame('Bearer hook', $request->header('Authorization')[0]);
        $this->assertSame(['recording_id' => $id, 'directory' => "/recordings/raw/{$id}"], $request->data());
    }

    public function test_recording_needs_a_recorded_interview_the_consent_a_panel_member_and_an_open_room(): void
    {
        $this->consentedInterview('refused');
        $this->join()->assertOk();
        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recording-not-allowed');
    }

    public function test_only_the_panel_may_record_and_the_room_must_be_open(): void
    {
        $this->consentedInterview();

        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:video-room-not-open');

        $this->join()->assertOk();
        $this->recording('start', $this->secondRecruiterToken)->assertNotFound();
        $this->recording('start', $this->seekerToken)->assertForbidden();
        $this->recording('stop', $this->seekerToken)->assertForbidden();
        $this->assertSame([], $this->sentToRoom('enable_recording'));
    }

    public function test_an_interview_that_is_not_marked_as_recorded_cannot_be_recorded(): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['format' => 'video', 'meeting_url' => null, 'starts_at' => $this->minutesFromNow(5)]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        $this->join()->assertOk();

        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recording-not-allowed');
    }

    public function test_a_recording_that_the_video_server_refuses_leaves_nothing_behind(): void
    {
        $this->consentedInterview();
        $this->join()->assertOk();
        $this->janusDown = true;

        $this->recording('start')->assertStatus(503);

        $this->assertSame(0, DB::table('recruitment_interview_recordings')->count());
    }

    public function test_a_withdrawn_consent_blocks_recording(): void
    {
        $this->consentedInterview();
        $this->join()->assertOk();

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/recording-consent/withdraw", [], $this->seekerToken)->assertOk();
        $this->assertSame('withdrawn', DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('recording_consent'));
        $this->recording('start')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recording-not-allowed');
    }

    public function test_withdrawing_the_consent_is_refused_after_the_recording_started(): void
    {
        $this->stoppedRecording();

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/recording-consent/withdraw", [], $this->seekerToken)
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:jobportal:error:recording-started');
        $this->assertSame('granted', DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('recording_consent'));
    }

    // ----- the converter webhook and storing -----

    public function test_a_converted_recording_is_moved_to_the_private_disk_and_kept_for_the_retention_period(): void
    {
        $id = $this->stoppedRecording();

        $this->converted($id);

        $row = DB::table('recruitment_interview_recordings')->find($id);
        $this->assertSame('ready', $row->status);
        $this->assertNull($row->source_file);
        $this->assertSame(1500, (int) $row->duration_seconds);
        $this->assertSame(strlen('webm-bytes'), (int) $row->size_bytes);
        $this->assertMatchesRegularExpression('#^interview-recordings/'.$this->interviewId().'/[0-9a-f]{32}\.webm$#', $row->path);
        Storage::assertExists($row->path);
        $this->assertSame('webm-bytes', Storage::get($row->path));
        $this->assertFileDoesNotExist("{$this->volume}/ready/{$id}.webm");

        $endsAt = Carbon::parse(DB::table('recruitment_interviews')->where('id', $this->interviewId())->value('ends_at'), 'UTC');
        $this->assertSame($endsAt->addDays(90)->getTimestamp(), Carbon::parse($row->expires_at, 'UTC')->getTimestamp());

        // The same report again changes nothing.
        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'ready', 'file' => "ready/{$id}.webm"])->assertNoContent();
        $this->assertSame($row->path, DB::table('recruitment_interview_recordings')->find($id)->path);
    }

    public function test_the_retention_period_follows_the_company_setting(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::RECORDING_RETENTION_DAYS, 30, null);
        $id = $this->stoppedRecording();

        $this->converted($id);

        $endsAt = Carbon::parse(DB::table('recruitment_interviews')->where('id', $this->interviewId())->value('ends_at'), 'UTC');
        $this->assertSame($endsAt->addDays(30)->getTimestamp(), Carbon::parse(DB::table('recruitment_interview_recordings')->find($id)->expires_at, 'UTC')->getTimestamp());
    }

    public function test_a_failed_conversion_marks_the_recording_failed_and_clears_the_volume(): void
    {
        $id = $this->stoppedRecording();
        File::ensureDirectoryExists("{$this->volume}/raw/{$id}");
        File::put("{$this->volume}/raw/{$id}/track.mjr", 'raw');

        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'failed'])->assertNoContent();

        $this->assertSame('failed', DB::table('recruitment_interview_recordings')->find($id)->status);
        $this->assertDirectoryDoesNotExist("{$this->volume}/raw/{$id}");
        $this->api('GET', "/company/interviews/{$this->interviewId()}/recordings/{$id}/file", [], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recording-not-ready');
    }

    public function test_a_converter_that_cannot_be_reached_marks_the_recording_failed_after_the_last_retry(): void
    {
        $id = $this->stoppedRecording();
        $this->converterDown = true;

        $this->assertThrows(fn () => $this->app->make(RecordingPipeline::class)->requestConversion($id), ExternalServiceUnavailable::class);
        $this->app->make(ConvertRecordingWhenStopped::class)->failed(new RecordingStopped($id, now()->toDateTimeImmutable()), new RuntimeException('down'));

        $this->assertSame('failed', DB::table('recruitment_interview_recordings')->find($id)->status);
    }

    public function test_a_converted_file_that_is_missing_from_the_volume_marks_the_recording_failed_after_the_last_retry(): void
    {
        Event::fake([RecordingConverted::class]);
        $id = $this->stoppedRecording();

        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'ready', 'file' => "ready/{$id}.webm"])->assertNoContent();
        $this->assertSame('processing', DB::table('recruitment_interview_recordings')->find($id)->status);
        Event::assertDispatched(RecordingConverted::class);

        $this->assertThrows(fn () => $this->app->make(RecordingPipeline::class)->store($id), RuntimeException::class);
        $this->app->make(StoreRecordingWhenConverted::class)->failed(new RecordingConverted($id, now()->toDateTimeImmutable()), new RuntimeException('missing'));

        $this->assertSame('failed', DB::table('recruitment_interview_recordings')->find($id)->status);
    }

    public function test_the_webhooks_need_the_shared_secret_and_a_valid_body(): void
    {
        $id = $this->stoppedRecording();

        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'failed'], null)->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:invalid-webhook-credentials');
        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'failed'], 'wrong')->assertUnauthorized();
        $this->webhook('events', [], null)->assertUnauthorized();

        config(['services.janus.webhook_secret' => '']);
        $this->webhook('events', [], '')->assertUnauthorized();
        config(['services.janus.webhook_secret' => 'hook']);

        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'ready', 'file' => '../../etc/passwd'])->assertUnprocessable();
        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'ready'])->assertUnprocessable();
        $this->webhook('recordings', ['recording_id' => $id, 'status' => 'done'])->assertUnprocessable();
        $this->assertSame('processing', DB::table('recruitment_interview_recordings')->find($id)->status);

        // The event handler of Janus can only send basic authentication.
        $this->app['auth']->forgetGuards();
        $this->json('POST', '/api/v1/internal/janus/events', [], ['Authorization' => 'Basic '.base64_encode('janus:hook')])->assertNoContent();
    }

    // ----- viewing, streaming and deleting -----

    public function test_the_team_lists_and_streams_a_ready_recording_with_ranges(): void
    {
        $id = $this->stoppedRecording();
        $this->converted($id, '0123456789');
        $base = "/company/interviews/{$this->interviewId()}/recordings";

        $this->api('GET', $base, [], $this->recruiterToken)->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.status', 'ready')->assertJsonPath('data.0.size_bytes', 10)->assertJsonMissingPath('data.0.path');
        $this->api('GET', $base, [], $this->adminToken)->assertOk();

        $whole = $this->api('GET', "{$base}/{$id}/file", [], $this->recruiterToken)->assertOk()->assertHeader('Content-Type', 'video/webm')->assertHeader('Accept-Ranges', 'bytes')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('0123456789', $whole->streamedContent());

        $part = $this->api('GET', "{$base}/{$id}/file", [], $this->recruiterToken, ['Range' => 'bytes=2-5'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10');
        $this->assertSame('2345', $part->streamedContent());

        $tail = $this->api('GET', "{$base}/{$id}/file", [], $this->recruiterToken, ['Range' => 'bytes=-3'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 7-9/10');
        $this->assertSame('789', $tail->streamedContent());

        $open = $this->api('GET', "{$base}/{$id}/file", [], $this->recruiterToken, ['Range' => 'bytes=8-'])->assertStatus(206);
        $this->assertSame('89', $open->streamedContent());

        $this->api('GET', "{$base}/{$id}/file", [], $this->recruiterToken, ['Range' => 'bytes=50-60'])->assertStatus(416)->assertHeader('Content-Range', 'bytes */10');
    }

    public function test_a_recording_is_visible_only_to_the_members_who_may_see_the_application(): void
    {
        $id = $this->stoppedRecording();
        $this->converted($id);
        $base = "/company/interviews/{$this->interviewId()}/recordings";

        $this->api('GET', $base, [], $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', $base, [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "{$base}/{$id}/file", [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', $base, [], $this->seekerToken)->assertForbidden();
        $this->api('GET', "{$base}/".($id + 999).'/file', [], $this->recruiterToken)->assertNotFound();
    }

    public function test_the_panel_and_the_company_admins_delete_a_recording_with_its_file(): void
    {
        $id = $this->stoppedRecording();
        $this->converted($id);
        $path = DB::table('recruitment_interview_recordings')->find($id)->path;
        $url = "/company/interviews/{$this->interviewId()}/recordings/{$id}";

        $this->api('DELETE', $url, [], $this->seekerToken)->assertForbidden();
        $this->api('DELETE', $url, [], $this->otherAdminToken)->assertNotFound();

        $this->api('DELETE', $url, [], $this->recruiterToken)->assertNoContent();

        Storage::assertMissing($path);
        $this->assertSame(0, DB::table('recruitment_interview_recordings')->count());
        $this->api('DELETE', $url, [], $this->recruiterToken)->assertNotFound();
    }

    public function test_a_company_admin_outside_the_panel_may_delete_but_a_recording_that_is_not_finished_stays(): void
    {
        $this->consentedInterview();
        // The company admin is on the panel here, so take them off it: the check under test is the admin role, not the panel.
        DB::table('recruitment_interview_participants')->where('interview_id', $this->interviewId())->delete();
        $this->join()->assertOk();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');
        $url = "/company/interviews/{$this->interviewId()}/recordings/{$id}";

        $this->api('DELETE', $url, [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recording-not-finished');

        $this->recording('stop')->assertOk();
        $this->converted($id);
        $this->api('DELETE', $url, [], $this->adminToken)->assertNoContent();
    }

    // ----- closing the room and retention -----

    public function test_closing_the_room_stops_a_recording_that_is_still_running(): void
    {
        $this->consentedInterview();
        $this->join()->assertOk();
        $id = (int) $this->recording('start')->assertCreated()->json('data.id');

        Carbon::setTestNow(now()->addMinutes(5 + 60 + 31));
        $this->assertSame(1, $this->app->make(CloseVideoRooms::class)->execute());

        $this->assertSame('processing', DB::table('recruitment_interview_recordings')->find($id)->status);
        $this->assertCount(2, $this->sentToRoom('enable_recording'));
        $this->assertSame(1, $this->convertRequests());
    }

    public function test_expired_recordings_are_deleted_and_stuck_conversions_given_up(): void
    {
        $id = $this->stoppedRecording();
        $this->converted($id);
        $path = DB::table('recruitment_interview_recordings')->find($id)->path;
        $stuck = DB::table('recruitment_interview_recordings')->insertGetId([
            'interview_id' => $this->interviewId(), 'status' => 'processing', 'started_by' => $this->recruiterId,
            'started_at' => now()->subDay(), 'stopped_at' => now()->subHours(7), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $fresh = DB::table('recruitment_interview_recordings')->insertGetId([
            'interview_id' => $this->interviewId(), 'status' => 'processing', 'started_by' => $this->recruiterId,
            'started_at' => now(), 'stopped_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->app->make(PurgeExpiredRecordings::class)->execute());
        $this->assertSame('failed', DB::table('recruitment_interview_recordings')->find($stuck)->status);
        $this->assertSame('processing', DB::table('recruitment_interview_recordings')->find($fresh)->status);

        Carbon::setTestNow(now()->addDays(5 + 90));
        $this->assertSame(1, $this->app->make(PurgeExpiredRecordings::class)->execute());

        $this->assertNull(DB::table('recruitment_interview_recordings')->find($id));
        Storage::assertMissing($path);
        $this->assertNotNull(DB::table('recruitment_interview_recordings')->find($stuck));
    }

    // ----- attendance -----

    private function janusEvent(string $name, int $session, array $data, int $room, ?int $at = null): array
    {
        return ['type' => 64, 'session_id' => $session, 'timestamp' => ($at ?? now()->getTimestamp()) * 1_000_000, 'event' => ['plugin' => 'janus.plugin.videoroom', 'data' => ['event' => $name, 'room' => $room, ...$data]]];
    }

    public function test_attendance_follows_the_events_of_the_video_server(): void
    {
        $this->consentedInterview();
        $team = $this->join()->assertOk()->json('data.credentials');
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/video/join", [], $this->seekerToken)->assertOk()->assertJsonPath('data.status', 'waiting');
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/video/admit", [], $this->recruiterToken)->assertOk();
        $candidate = $this->api('POST', "/me/applications/{$this->applicationId}/interview/video/join", [], $this->seekerToken)->json('data.credentials');
        $room = $team['room_id'];
        $this->assertNotSame($team['display'], $candidate['display']);

        $this->webhook('events', [
            $this->janusEvent('joined', 901, ['display' => $team['display']], $room, now()->getTimestamp()),
            $this->janusEvent('joined', 902, ['display' => $candidate['display']], $room, now()->addMinute()->getTimestamp()),
            // A name nobody signed, a different room and an event for a room nobody owns are all ignored.
            $this->janusEvent('joined', 903, ['display' => $this->seekerId.'.0000000000000000'], $room),
            $this->janusEvent('joined', 904, ['display' => 'Mallory'], $room),
            $this->janusEvent('joined', 905, ['display' => $team['display']], $room + 1),
            ['type' => 1, 'session_id' => 906],
        ])->assertNoContent();
        $this->webhook('events', $this->janusEvent('joined', 901, ['display' => $team['display']], $room))->assertNoContent();

        $this->webhook('events', $this->janusEvent('leaving', 902, [], $room, now()->addMinutes(20)->getTimestamp()))->assertNoContent();

        $rows = $this->api('GET', "/company/interviews/{$this->interviewId()}/attendance", [], $this->recruiterToken)->assertOk()->json('data');
        $this->assertCount(2, $rows);
        $this->assertSame(['user_id' => $this->recruiterId, 'role' => 'company', 'joined_at' => now()->toAtomString(), 'left_at' => null], $rows[0]);
        $this->assertSame([$this->seekerId, 'candidate', now()->addMinute()->toAtomString(), now()->addMinutes(20)->toAtomString()], [$rows[1]['user_id'], $rows[1]['role'], $rows[1]['joined_at'], $rows[1]['left_at']]);

        $this->api('GET', "/company/interviews/{$this->interviewId()}/attendance", [], $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/interviews/{$this->interviewId()}/attendance", [], $this->seekerToken)->assertForbidden();
    }

    // ----- erasure -----

    public function test_erasing_the_applicant_deletes_the_recordings_and_attendance_of_their_interviews(): void
    {
        $id = $this->stoppedRecording();
        $this->converted($id);
        $path = DB::table('recruitment_interview_recordings')->find($id)->path;
        DB::table('recruitment_interview_attendances')->insert(['interview_id' => $this->interviewId(), 'user_id' => $this->seekerId, 'role' => 'candidate', 'joined_at' => now()]);

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        Storage::assertMissing($path);
        $this->assertSame(0, DB::table('recruitment_interview_recordings')->count());
        $this->assertSame(0, DB::table('recruitment_interview_attendances')->count());
    }
}
