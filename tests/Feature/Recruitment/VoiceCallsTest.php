<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Infrastructure\Voice\AriEventListener;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 3, part 2: the team joins the voice bridge from the browser, dials the candidate through the voice server (ARI), and the events of the
 * voice server become the call log, the attendance and the missed-call notice. The voice server is faked.
 */
final class VoiceCallsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    /** How many channels the fake bridge holds. */
    private int $bridgeChannels = 1;

    /** The fake voice server answers 503 to everything while this is set. */
    private bool $ariDown = false;

    /** The fake voice server refuses to originate a channel while this is set. */
    private bool $dialRefused = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VOICE_ENABLED, true, null);
        $this->fakeAsterisk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- the fake voice server -----

    private function fakeAsterisk(): void
    {
        config(['services.asterisk' => [
            'ari_url' => 'http://asterisk.test:8088/ari', 'ari_user' => 'ari', 'ari_password' => 'secret', 'wss_url' => 'wss://voice.example.test/ws',
            'sip_domain' => null, 'caller_id' => '+622150000000', 'trunk_endpoint' => 'trunk',
        ], 'services.turn' => ['urls' => 'turn:turn.example.test:3478?transport=udp', 'secret' => 'turn-secret']]);

        Http::fake([
            'asterisk.test:8088/*' => function (Request $request) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);

                return match (true) {
                    $this->ariDown => Http::response('', 503),
                    $this->dialRefused && $request->method() === 'POST' && $path === '/ari/channels' => Http::response('', 500),
                    $request->method() === 'GET' && str_starts_with($path, '/ari/bridges/') => Http::response(['channels' => array_fill(0, $this->bridgeChannels, 'channel')]),
                    default => Http::response([]),
                };
            },
        ]);
    }

    /** @return list<Request> the requests sent to the voice server with that method whose path starts with the prefix */
    private function ari(string $method, string $prefix): array
    {
        $found = [];

        foreach (Http::recorded() as [$request]) {
            if ($request->method() === strtoupper($method) && str_starts_with((string) parse_url($request->url(), PHP_URL_PATH), '/ari'.$prefix)) {
                $found[] = $request;
            }
        }

        return $found;
    }

    /** @return array<string, string> */
    private function queryOf(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return array_map('strval', $query);
    }

    // ----- helpers -----

    private function minutesFromNow(int $minutes): string
    {
        return now()->addMinutes($minutes)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** A phone interview of the first application that starts in five minutes (so calls are possible) and that the job seeker confirmed with a number. */
    private function confirmedVoiceInterview(array $overrides = [], string $format = 'voice', ?array $confirm = null): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['format' => $format, 'meeting_url' => null, 'starts_at' => $this->minutesFromNow(5), ...$overrides]);

        if (DB::table('recruitment_interviews')->exists()) {
            $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $schedule, $this->recruiterToken)->assertSuccessful();
        } else {
            $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertNoContent();
        }

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", $confirm ?? ['phone' => '0812-3456-7890'], $this->seekerToken)->assertOk();
    }

    private function team(string $action, ?string $token = null, string $method = 'POST'): TestResponse
    {
        return $this->api($method, "/company/applications/{$this->applicationId}/interview/voice/{$action}", [], $token ?? $this->recruiterToken);
    }

    private function interviewId(): int
    {
        return (int) DB::table('recruitment_interviews')->orderByDesc('id')->value('id');
    }

    /** Joins as the interviewer and dials; returns the call id. */
    private function dial(): int
    {
        $this->team('join')->assertOk();

        return (int) $this->team('call')->assertCreated()->json('data.id');
    }

    private function channelOf(int $callId): string
    {
        return (string) DB::table('recruitment_interview_calls')->where('id', $callId)->value('channel_id');
    }

    /** @param  array<string, mixed>  $event */
    private function hear(array $event): void
    {
        $this->app->make(AriEventListener::class)->handleMessage((string) json_encode($event));
    }

    private function callStatus(int $callId): string
    {
        return (string) DB::table('recruitment_interview_calls')->where('id', $callId)->value('status');
    }

    private function notificationTypes(int $userId): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'interview.%')->pluck('type')->all();
    }

    // ----- joining -----

    public function test_the_interviewer_joins_and_gets_a_browser_account_on_a_new_bridge(): void
    {
        $this->confirmedVoiceInterview();
        $interviewId = $this->interviewId();

        $join = $this->team('join')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.username', "t{$interviewId}-{$this->recruiterId}")->assertJsonPath('data.extension', "interview-{$interviewId}")
            ->assertJsonPath('data.ws_url', 'wss://voice.example.test/ws')->assertJsonPath('data.sip_domain', 'voice.example.test');
        $password = $join->json('data.password');
        $this->assertSame(24, strlen($password));
        $this->assertSame(['turn:turn.example.test:3478?transport=udp'], $join->json('data.ice_servers.0.urls'));
        $this->assertEqualsWithDelta(now()->addMinutes(5 + 60 + 30)->getTimestamp(), Carbon::parse($join->json('data.expires_at'))->getTimestamp(), 5);

        $bridge = $this->ari('post', '/bridges/');
        $this->assertCount(1, $bridge);
        $this->assertStringEndsWith("/bridges/interview-{$interviewId}", (string) parse_url($bridge[0]->url(), PHP_URL_PATH));
        $this->assertSame('mixing', $this->queryOf($bridge[0])['type']);

        $objects = [];

        foreach ($this->ari('put', '/asterisk/config/dynamic/res_pjsip/') as $request) {
            $parts = explode('/', (string) parse_url($request->url(), PHP_URL_PATH));
            $fields = [];

            foreach ($request->data()['fields'] as $field) {
                $fields[$field['attribute']] = $field['value'];
            }

            $objects[$parts[count($parts) - 2]] = $fields;
        }

        $this->assertSame(['auth', 'aor', 'endpoint'], array_keys($objects));
        $this->assertSame($password, $objects['auth']['password']);
        $this->assertSame('jobportal-team', $objects['endpoint']['context']);
        $this->assertSame('yes', $objects['endpoint']['webrtc']);
        $this->assertSame("t{$interviewId}-{$this->recruiterId}", $objects['endpoint']['aors']);
        $this->assertNotNull(DB::table('recruitment_interviews')->value('voice_opened_at'));
        $this->assertSame(["t{$interviewId}-{$this->recruiterId}"], DB::table('recruitment_interview_voice_endpoints')->pluck('username')->all());

        // Joining again opens nothing new and replaces the password.
        $again = $this->team('join')->assertOk()->json('data.password');
        $this->assertNotSame($password, $again);
        $this->assertCount(1, $this->ari('post', '/bridges/'));
        $this->assertSame(1, DB::table('recruitment_interview_voice_endpoints')->count());
    }

    public function test_only_the_panel_joins_and_only_a_callable_interview(): void
    {
        $this->confirmedVoiceInterview();
        $this->team('join', $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-an-interview-participant');
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/voice/join", [], $this->seekerToken)->assertForbidden();
        $this->team('join', $this->otherAdminToken)->assertNotFound();
        $this->assertSame(0, DB::table('recruitment_interview_voice_endpoints')->count());

        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VOICE_ENABLED, false, null);
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:voice-disabled');
    }

    public function test_the_interview_must_be_confirmed_held_by_phone_and_inside_its_window(): void
    {
        $this->confirmedVoiceInterview(['starts_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:voice-window-closed');

        $this->confirmedVoiceInterview(['format' => 'online', 'meeting_url' => 'https://meet.example.test/a'], 'online', []);
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:not-a-voice-interview');

        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VIDEO_ENABLED, true, null);
        $this->confirmedVoiceInterview(format: 'video', confirm: []);
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:no-phone-number');
    }

    public function test_a_video_interview_with_a_fallback_number_can_be_joined_by_phone(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VIDEO_ENABLED, true, null);
        $this->confirmedVoiceInterview(format: 'video', confirm: ['fallback_phone' => '08123456789']);

        $this->team('join')->assertOk();
        $this->team('call')->assertCreated();
        $this->assertSame('+628123456789', DB::table('recruitment_interview_calls')->value('number'));
    }

    public function test_a_voice_server_that_is_down_or_not_configured_answers_503_and_leaves_no_bridge(): void
    {
        $this->confirmedVoiceInterview();

        $this->ariDown = true;
        $this->team('join')->assertStatus(503);
        $this->assertNull(DB::table('recruitment_interviews')->value('voice_opened_at'));

        $this->ariDown = false;
        config(['services.asterisk.ari_url' => null]);
        $this->team('join')->assertStatus(503);
    }

    // ----- dialling -----

    public function test_the_team_dials_the_candidate_through_the_trunk_as_the_platform_number(): void
    {
        $this->confirmedVoiceInterview();

        $callId = $this->dial();

        $row = DB::table('recruitment_interview_calls')->first();
        $this->assertSame('dialing', $row->status);
        $this->assertSame('+6281234567890', $row->number);
        $this->assertSame($this->recruiterId, (int) $row->started_by);
        $this->assertMatchesRegularExpression('/^call-[0-9a-f]{16}$/', $row->channel_id);

        $channels = $this->ari('post', '/channels');
        $this->assertCount(1, $channels);
        $this->assertSame([
            'endpoint' => 'PJSIP/+6281234567890@trunk', 'app' => 'jobportal', 'appArgs' => "candidate,{$callId}", 'channelId' => $row->channel_id, 'callerId' => '+622150000000', 'timeout' => '45',
        ], $this->queryOf($channels[0]));

        $this->team('call')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:call-in-progress');
    }

    public function test_the_team_must_be_in_the_call_before_it_dials(): void
    {
        $this->confirmedVoiceInterview();

        $this->team('call')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:team-not-connected');

        $this->team('join')->assertOk();
        $this->bridgeChannels = 0;
        $this->team('call')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:team-not-connected');
        $this->assertSame(0, DB::table('recruitment_interview_calls')->count());
    }

    public function test_a_dial_the_voice_server_refuses_leaves_no_call_and_uses_no_attempt(): void
    {
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();

        $this->dialRefused = true;
        $this->team('call')->assertStatus(503);
        $this->assertSame(0, DB::table('recruitment_interview_calls')->count());

        $this->dialRefused = false;
        $this->team('call')->assertCreated();
    }

    public function test_the_attempts_the_quota_and_the_capacity_are_capped(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal(InterviewSettings::VOICE_MAX_ATTEMPTS, 2, null);
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();

        foreach ([1, 2] as $attempt) {
            $callId = (int) $this->team('call')->assertCreated()->json('data.id');
            $this->hear(['type' => 'ChannelDestroyed', 'cause' => 19, 'channel' => ['id' => $this->channelOf($callId)]]);
        }

        $this->team('call')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:call-attempts-used');

        // A new time starts the count again, and then the month's minutes and the platform's capacity are the limits.
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();
        $store->setGlobal(InterviewSettings::VOICE_MONTHLY_MINUTES, 1, null);
        DB::table('recruitment_interview_calls')->insert([
            'interview_id' => $this->interviewId(), 'started_by' => $this->recruiterId, 'status' => 'ended', 'number' => '+6281234567890', 'channel_id' => 'call-used', 'dialed_at' => now(),
            'answered_at' => now(), 'ended_at' => now(), 'duration_seconds' => 60, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->team('call')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:voice-quota-used');

        $store->setGlobal(InterviewSettings::VOICE_MONTHLY_MINUTES, 600, null);
        $store->setGlobal(InterviewSettings::VOICE_MAX_CONCURRENT_CALLS, 1, null);
        DB::table('recruitment_interview_calls')->insert([
            'interview_id' => (int) DB::table('recruitment_interviews')->orderBy('id')->value('id'), 'started_by' => $this->recruiterId, 'status' => 'ringing', 'number' => '+6281234567891',
            'channel_id' => 'call-other', 'dialed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->team('call')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:voice-capacity-full');
    }

    public function test_the_team_hangs_up_a_call_in_progress(): void
    {
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();

        $this->team('hangup')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:no-live-call');

        $callId = (int) $this->team('call')->assertCreated()->json('data.id');
        $this->team('hangup')->assertOk()->assertJsonPath('data.id', $callId);

        $deleted = $this->ari('delete', '/channels/');
        $this->assertCount(1, $deleted);
        $this->assertStringEndsWith('/channels/'.$this->channelOf($callId), (string) parse_url($deleted[0]->url(), PHP_URL_PATH));
        $this->team('hangup', $this->adminToken)->assertForbidden();
    }

    public function test_the_call_log_is_visible_to_the_members_who_see_the_application(): void
    {
        $this->confirmedVoiceInterview();
        $callId = $this->dial();
        $interviewId = $this->interviewId();

        $this->api('GET', "/company/interviews/{$interviewId}/calls", [], $this->adminToken)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $callId)->assertJsonPath('data.0.status', 'dialing')->assertJsonMissingPath('data.0.number');
        $this->api('GET', "/company/interviews/{$interviewId}/calls", [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "/company/interviews/{$interviewId}/calls", [], $this->seekerToken)->assertForbidden();
    }

    // ----- what the voice server reports -----

    public function test_an_answered_call_enters_the_bridge_is_capped_and_ends_with_its_duration(): void
    {
        Carbon::setTestNow(now()->startOfSecond());
        $this->confirmedVoiceInterview();
        $callId = $this->dial();
        $channel = $this->channelOf($callId);
        $interviewId = $this->interviewId();

        $this->hear(['type' => 'ChannelStateChange', 'channel' => ['id' => $channel, 'state' => 'Ringing']]);
        $this->assertSame('ringing', $this->callStatus($callId));

        $this->hear(['type' => 'StasisStart', 'args' => ['candidate', (string) $callId], 'channel' => ['id' => $channel, 'name' => 'PJSIP/trunk-00000001', 'state' => 'Up']]);
        $this->assertSame('answered', $this->callStatus($callId));

        $added = $this->ari('post', "/bridges/interview-{$interviewId}/addChannel");
        $this->assertSame([$channel], array_column(array_map(fn (Request $r): array => $this->queryOf($r), $added), 'channel'));
        $cap = $this->ari('post', "/channels/{$channel}/variable");
        $this->assertSame(['variable' => 'TIMEOUT(absolute)', 'value' => '3600'], $this->queryOf($cap[0]));
        $this->assertSame(['candidate'], DB::table('recruitment_interview_attendances')->where('channel_id', $channel)->pluck('role')->all());

        Carbon::setTestNow(now()->addSeconds(125));
        $this->hear(['type' => 'ChannelDestroyed', 'cause' => 16, 'channel' => ['id' => $channel]]);

        $row = DB::table('recruitment_interview_calls')->where('id', $callId)->first();
        $this->assertSame('ended', $row->status);
        $this->assertSame(125, (int) $row->duration_seconds);
        $this->assertSame(16, (int) $row->hangup_cause);
        $this->assertNotNull(DB::table('recruitment_interview_attendances')->where('channel_id', $channel)->value('left_at'));
        $this->assertNotContains('interview.call_missed', $this->notificationTypes($this->seekerId));

        // The same event again changes nothing.
        $this->hear(['type' => 'ChannelDestroyed', 'cause' => 16, 'channel' => ['id' => $channel]]);
        $this->assertSame(125, (int) DB::table('recruitment_interview_calls')->where('id', $callId)->value('duration_seconds'));
    }

    public function test_the_call_cap_is_what_is_left_of_the_month_when_that_is_less(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal(InterviewSettings::VOICE_MONTHLY_MINUTES, 10, null);
        $this->confirmedVoiceInterview();
        $callId = $this->dial();
        DB::table('recruitment_interview_calls')->insert([
            'interview_id' => $this->interviewId(), 'started_by' => $this->recruiterId, 'status' => 'ended', 'number' => '+6281234567890', 'channel_id' => 'call-used', 'dialed_at' => now(),
            'answered_at' => now(), 'ended_at' => now(), 'duration_seconds' => 420, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $channel = $this->channelOf($callId);
        $this->hear(['type' => 'StasisStart', 'args' => ['candidate', (string) $callId], 'channel' => ['id' => $channel, 'name' => 'PJSIP/trunk-00000001']]);

        $this->assertSame('180', $this->queryOf($this->ari('post', "/channels/{$channel}/variable")[0])['value']);
    }

    public function test_a_call_nobody_answered_is_a_missed_call_and_the_candidate_is_told(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VOICE_MAX_ATTEMPTS, 10, null);
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();

        $expected = [19 => 'no_answer', 17 => 'busy', 1 => 'failed'];
        $missed = 0;

        foreach ($expected as $cause => $status) {
            $callId = (int) $this->team('call')->assertCreated()->json('data.id');
            $this->hear(['type' => 'ChannelDestroyed', 'cause' => $cause, 'channel' => ['id' => $this->channelOf($callId)]]);

            $this->assertSame($status, $this->callStatus($callId));
            $this->assertNull(DB::table('recruitment_interview_calls')->where('id', $callId)->value('duration_seconds'));
            $missed += $status === 'failed' ? 0 : 1;
            $this->assertCount($missed, array_filter($this->notificationTypes($this->seekerId), fn (string $type): bool => $type === 'interview.call_missed'));
        }
    }

    public function test_the_team_browser_enters_the_bridge_only_with_an_account_handed_out_for_the_interview(): void
    {
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();
        $interviewId = $this->interviewId();
        $bridge = "interview-{$interviewId}";
        $name = "PJSIP/t{$interviewId}-{$this->recruiterId}-00000007";

        $this->hear(['type' => 'StasisStart', 'args' => ['team', $bridge], 'channel' => ['id' => 'team-channel', 'name' => $name]]);

        $this->assertCount(1, $this->ari('post', '/channels/team-channel/answer'));
        $this->assertSame(['team-channel'], array_column(array_map(fn (Request $r): array => $this->queryOf($r), $this->ari('post', "/bridges/{$bridge}/addChannel")), 'channel'));
        $this->assertSame(['company'], DB::table('recruitment_interview_attendances')->where('channel_id', 'team-channel')->pluck('role')->all());
        $this->assertSame($this->recruiterId, (int) DB::table('recruitment_interview_attendances')->where('channel_id', 'team-channel')->value('user_id'));

        $this->hear(['type' => 'ChannelDestroyed', 'cause' => 16, 'channel' => ['id' => 'team-channel']]);
        $this->assertNotNull(DB::table('recruitment_interview_attendances')->where('channel_id', 'team-channel')->value('left_at'));

        // An account nobody handed out for this interview, another interview's bridge and a bad bridge name are all turned away.
        foreach ([
            ['team', $bridge, "PJSIP/t{$interviewId}-999-00000008"],
            ['team', 'interview-999999', $name],
            ['team', 'not-a-bridge', $name],
            ['team', $bridge, 'PJSIP/trunk-00000009'],
        ] as $index => [$kind, $bridgeId, $channelName]) {
            $this->hear(['type' => 'StasisStart', 'args' => [$kind, $bridgeId], 'channel' => ['id' => "stranger-{$index}", 'name' => $channelName]]);
            $this->assertCount(1, $this->ari('delete', "/channels/stranger-{$index}"));
        }

        $this->assertSame(1, DB::table('recruitment_interview_attendances')->count());
    }

    public function test_events_that_match_nothing_are_ignored(): void
    {
        $this->confirmedVoiceInterview();

        $this->hear(['type' => 'StasisStart', 'args' => ['candidate', '1'], 'channel' => ['id' => 'call-unknown', 'name' => 'PJSIP/trunk-00000001']]);
        $this->hear(['type' => 'ChannelDestroyed', 'cause' => 16, 'channel' => ['id' => 'call-unknown']]);
        $this->hear(['type' => 'PlaybackFinished']);
        $this->hear(['type' => 'ChannelStateChange']);
        $this->app->make(AriEventListener::class)->handleMessage('not json');

        $this->assertSame(0, DB::table('recruitment_interview_calls')->count());
        $this->assertCount(1, $this->ari('delete', '/channels/call-unknown'));
    }

    public function test_the_event_stream_address_carries_the_application_and_the_credentials(): void
    {
        $this->assertSame(
            'ws://asterisk.test:8088/ari/events?app=jobportal&api_key=ari%3Asecret&subscribeAll=false',
            $this->app->make(AriEventListener::class)->streamUrl(),
        );
    }

    // ----- closing -----

    public function test_a_voice_session_is_closed_when_its_window_ends(): void
    {
        $this->confirmedVoiceInterview();
        $callId = $this->dial();
        $interviewId = $this->interviewId();

        Artisan::call('recruitment:close-voice-sessions');
        $this->assertNull(DB::table('recruitment_interviews')->value('voice_closed_at'));

        Carbon::setTestNow(now()->addHours(3));
        $this->assertSame(0, Artisan::call('recruitment:close-voice-sessions'));

        $this->assertNotNull(DB::table('recruitment_interviews')->value('voice_closed_at'));
        $this->assertSame(0, DB::table('recruitment_interview_voice_endpoints')->count());
        $this->assertCount(1, $this->ari('delete', '/channels/'.$this->channelOf($callId)));
        $this->assertCount(1, $this->ari('delete', "/bridges/interview-{$interviewId}"));
        $this->assertCount(3, $this->ari('delete', '/asterisk/config/dynamic/res_pjsip/'));
    }

    public function test_a_session_that_cannot_be_closed_now_is_tried_again(): void
    {
        $this->confirmedVoiceInterview();
        $this->team('join')->assertOk();

        Carbon::setTestNow(now()->addHours(3));
        $this->ariDown = true;
        Artisan::call('recruitment:close-voice-sessions');
        $this->assertNull(DB::table('recruitment_interviews')->value('voice_closed_at'));
        $this->assertSame(1, DB::table('recruitment_interview_voice_endpoints')->count());

        $this->ariDown = false;
        Artisan::call('recruitment:close-voice-sessions');
        $this->assertNotNull(DB::table('recruitment_interviews')->value('voice_closed_at'));
    }

    public function test_a_cancelled_interview_closes_its_session_and_a_lost_call_is_closed_too(): void
    {
        $this->confirmedVoiceInterview();
        $callId = $this->dial();

        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();
        Artisan::call('recruitment:close-voice-sessions');
        $this->assertNotNull(DB::table('recruitment_interviews')->value('voice_closed_at'));

        // The voice server never reported the end of this call; once longer than any call can last it is closed as failed.
        Carbon::setTestNow(now()->addHours(5));
        Artisan::call('recruitment:close-voice-sessions');
        $this->assertSame('failed', $this->callStatus($callId));
    }
}
