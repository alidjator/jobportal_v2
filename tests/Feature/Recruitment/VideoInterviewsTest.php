<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Src\Company\Domain\MemberPreset;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\Video\CloseVideoRooms;
use Src\Recruitment\Application\Video\VideoChannelAuthorizer;
use Src\Recruitment\Infrastructure\Video\LobbyChangedBroadcast;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyMembership;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** Form 1 part 2 of the tele-interview (`docs/design/phase-7-tele-interview.md`): rooms on the video server, join tokens, the lobby and closing the rooms. */
final class VideoInterviewsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private const string WS_URL = 'wss://video.example.test/janus';

    private int $job;

    private int $applicationId;

    /** The fake video server answers 503 to everything while this is set. */
    private bool $janusDown = false;

    /** The fake Admin API refuses `add_token` while this is set. */
    private bool $tokensRefused = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->fakeJanus();
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal(InterviewSettings::VIDEO_ENABLED, true, null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- the fake video server -----

    private function fakeJanus(): void
    {
        config([
            'services.janus' => [
                'http_url' => 'http://janus.test:8088/janus', 'admin_url' => 'http://janus.test:7088/admin', 'admin_secret' => 'admin-secret',
                'api_token' => 'api-token', 'ws_url' => self::WS_URL, 'webhook_secret' => 'hook',
            ],
            'services.turn' => ['urls' => 'turn:turn.example.test:3478?transport=udp,turns:turn.example.test:5349', 'secret' => 'turn-secret'],
        ]);

        Http::fake([
            'janus.test:8088/*' => function (Request $request) {
                if ($this->janusDown) {
                    return Http::response('', 503);
                }

                $path = parse_url($request->url(), PHP_URL_PATH);

                return match (true) {
                    $path === '/janus' => Http::response(['janus' => 'success', 'data' => ['id' => 111]]),
                    $path === '/janus/111' && ($request->data()['janus'] ?? null) === 'attach' => Http::response(['janus' => 'success', 'data' => ['id' => 222]]),
                    $path === '/janus/111/222' => Http::response(['janus' => 'success', 'plugindata' => ['plugin' => 'janus.plugin.videoroom', 'data' => ['videoroom' => $request->data()['body']['request'] === 'create' ? 'created' : 'destroyed']]]),
                    default => Http::response(['janus' => 'success']),
                };
            },
            'janus.test:7088/*' => fn (Request $request) => $this->janusDown
                ? Http::response('', 503)
                : Http::response($this->tokensRefused && ($request->data()['janus'] ?? null) === 'add_token' ? ['janus' => 'error', 'error' => ['code' => 403, 'reason' => 'Unauthorized']] : ['janus' => 'success']),
        ]);
    }

    /** @return list<array<string, mixed>> the bodies of the Admin API requests (`add_token`, `remove_token`) */
    private function sent(string $name): array
    {
        return $this->recorded(fn (array $data, string $url): bool => str_contains($url, ':7088') && ($data['janus'] ?? null) === $name);
    }

    /** @return list<array<string, mixed>> the bodies of the VideoRoom requests (`create`, `destroy`) */
    private function sentToRoom(string $name): array
    {
        return $this->recorded(fn (array $data, string $url): bool => str_contains($url, ':8088') && ($data['body']['request'] ?? null) === $name);
    }

    /** @return list<array<string, mixed>> */
    private function recorded(callable $matches): array
    {
        $bodies = [];

        foreach (Http::recorded() as [$request]) {
            if ($matches($request->data(), $request->url())) {
                $bodies[] = $request->data();
            }
        }

        return $bodies;
    }

    // ----- helpers -----

    private function videoAt(string $startsAt, array $overrides = []): array
    {
        return $this->interviewSchedule(['format' => 'video', 'meeting_url' => null, 'starts_at' => $startsAt, ...$overrides]);
    }

    private function minutesFromNow(int $minutes): string
    {
        return now()->addMinutes($minutes)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function stage(): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
    }

    /** A video interview of the first application that starts in five minutes (so the room is open) and that the job seeker confirmed. */
    private function confirmedVideoInterview(array $overrides = []): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage(), 'interview' => $this->videoAt($this->minutesFromNow(5), $overrides)], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
    }

    private function team(string $action, ?string $token = null, string $method = 'POST', ?int $id = null): TestResponse
    {
        return $this->api($method, '/company/applications/'.($id ?? $this->applicationId)."/interview/video/{$action}", [], $token ?? $this->recruiterToken);
    }

    private function mine(string $action, string $method = 'POST', ?int $id = null): TestResponse
    {
        return $this->api($method, '/me/applications/'.($id ?? $this->applicationId)."/interview/video/{$action}", [], $this->seekerToken);
    }

    private function roomRow(): object
    {
        return DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->first();
    }

    private function member(int $userId, string $role): Actor
    {
        return Actor::companyMember($userId)->withMembership(new CompanyMembership($this->companyId, $role === 'recruiter' ? 'member' : $role, $role === 'recruiter' ? MemberPreset::Recruiter->permissionNames() : []));
    }

    // ----- joining as the panel -----

    public function test_the_interviewer_joins_and_the_room_is_created_once_with_a_token_for_each_join(): void
    {
        $this->confirmedVideoInterview(['participant_ids' => [$this->adminId]]);

        $first = $this->team('join')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.status', 'ready')->assertJsonPath('data.credentials.ws_url', self::WS_URL);
        $credentials = $first->json('data.credentials');
        $this->assertIsInt($credentials['room_id']);
        $this->assertSame(16, strlen($credentials['pin']));
        $this->assertSame(64, strlen($credentials['token']));
        $this->assertSame(now()->addMinutes(5 + 60 + 30)->getTimestamp(), Carbon::parse($credentials['expires_at'])->getTimestamp());
        $this->assertSame(['turn:turn.example.test:3478?transport=udp', 'turns:turn.example.test:5349'], $credentials['ice_servers'][0]['urls']);
        $this->assertMatchesRegularExpression('/^\d+:'.$this->recruiterId.'$/', $credentials['ice_servers'][0]['username']);

        // The panel member joins the same room; a rejoin gets the same token and creates nothing.
        $second = $this->team('join', $this->adminToken)->assertOk()->json('data.credentials');
        $again = $this->team('join')->assertOk()->json('data.credentials');

        $this->assertSame($credentials['room_id'], $second['room_id']);
        $this->assertNotSame($credentials['token'], $second['token']);
        $this->assertSame($credentials['token'], $again['token']);
        $this->assertCount(1, $this->sentToRoom('create'));
        $this->assertSame($credentials['room_id'], $this->sentToRoom('create')[0]['body']['room']);
        $this->assertSame(['janus.plugin.videoroom'], $this->sent('add_token')[0]['plugins']);
        $this->assertSame('api-token', $this->sentToRoom('create')[0]['token']);
        $this->assertSame('admin-secret', $this->sent('add_token')[0]['admin_secret']);

        $row = $this->roomRow();
        $this->assertSame($credentials['room_id'], (int) $row->video_room_id);
        $this->assertNotSame($credentials['pin'], $row->video_room_pin);
        $this->assertSame(2, DB::table('recruitment_interview_video_tokens')->count());
    }

    public function test_only_the_interviewer_and_the_panel_may_join(): void
    {
        $this->confirmedVideoInterview();

        $this->team('join', $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-an-interview-participant');
        $this->team('lobby', $this->adminToken, 'GET')->assertForbidden();
        $this->team('admit', $this->adminToken)->assertForbidden();
        $this->team('join', $this->secondRecruiterToken)->assertNotFound();
        $this->team('join', $this->otherAdminToken)->assertNotFound();
        $this->team('join', $this->seekerToken)->assertForbidden();
        $this->assertSame([], $this->sentToRoom('create'));
    }

    public function test_a_room_opens_ten_minutes_before_the_start_and_shuts_thirty_minutes_after_the_end(): void
    {
        $this->confirmedVideoInterview(['starts_at' => $this->minutesFromNow(30)]);

        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-window-closed');
        $this->mine('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-window-closed');

        Carbon::setTestNow(now()->addMinutes(20));
        $this->team('join')->assertOk();

        Carbon::setTestNow(now()->addMinutes(10 + 60 + 30));
        $this->team('join')->assertOk();

        Carbon::setTestNow(now()->addMinutes(1));
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-window-closed');
    }

    public function test_a_video_interview_must_be_confirmed_and_switched_on(): void
    {
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-interview');

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage(), 'interview' => $this->videoAt($this->minutesFromNow(5))], $this->recruiterToken)->assertNoContent();
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-confirmed');
        $this->mine('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-confirmed');

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VIDEO_ENABLED, false, null);
        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-disabled');
        $this->mine('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-disabled');
    }

    public function test_an_interview_that_is_not_video_has_no_room(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage(), 'interview' => $this->interviewSchedule(['starts_at' => $this->minutesFromNow(5)])], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();

        $this->team('join')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:not-a-video-interview');
        $this->mine('lobby', 'GET')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:not-a-video-interview');
    }

    public function test_the_number_of_rooms_open_at_once_is_capped(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VIDEO_MAX_CONCURRENT_ROOMS, 1, null);
        $this->confirmedVideoInterview();
        $secondJob = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $second = (int) $this->apply($secondJob)->assertCreated()->json('data.id');
        $this->api('POST', "/company/applications/{$second}/move", ['stage_id' => $this->stage(), 'interview' => $this->videoAt($this->minutesFromNow(5))], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$second}/interview/confirm", [], $this->seekerToken)->assertOk();

        $this->team('join')->assertOk();
        $this->team('join', id: $second)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:video-capacity-full');
        $this->assertCount(1, $this->sentToRoom('create'));
        $this->team('join')->assertOk();
    }

    // ----- the video server fails -----

    public function test_a_video_server_that_is_down_or_not_configured_answers_503_and_leaves_no_room(): void
    {
        $this->confirmedVideoInterview();

        $this->janusDown = true;
        $this->team('join')->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:video-server-unavailable');
        $this->assertNull($this->roomRow()->video_room_id);

        $this->janusDown = false;
        config(['services.janus.admin_secret' => '']);
        $this->team('join')->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:video-server-unavailable');
    }

    public function test_a_room_whose_token_was_refused_is_destroyed_and_not_kept(): void
    {
        $this->confirmedVideoInterview();

        $this->tokensRefused = true;
        $this->team('join')->assertStatus(503);

        $this->assertNull($this->roomRow()->video_room_id);
        $this->assertCount(1, $this->sentToRoom('destroy'));
        $this->assertSame($this->sentToRoom('create')[0]['body']['secret'], $this->sentToRoom('destroy')[0]['body']['secret']);

        $this->tokensRefused = false;
        $this->team('join')->assertOk();
    }

    // ----- the lobby -----

    public function test_the_candidate_waits_in_the_lobby_until_the_panel_admits_them(): void
    {
        Event::fake([LobbyChangedBroadcast::class]);
        $this->confirmedVideoInterview();

        $this->mine('lobby', 'GET')->assertOk()->assertJsonPath('data.state', 'idle');
        $this->team('lobby', method: 'GET')->assertOk()->assertJsonPath('data.state', 'idle');

        $this->mine('join')->assertOk()->assertJsonPath('data.status', 'waiting')->assertJsonPath('data.credentials', null);
        $this->mine('join')->assertOk()->assertJsonPath('data.status', 'waiting');
        $this->assertSame([], $this->sentToRoom('create'));
        Event::assertDispatchedTimes(LobbyChangedBroadcast::class, 1);
        Event::assertDispatched(LobbyChangedBroadcast::class, fn (LobbyChangedBroadcast $event): bool => $event->state === 'waiting' && $event->interviewId === (int) $this->roomRow()->id);

        $this->team('lobby', method: 'GET')->assertOk()->assertJsonPath('data.state', 'waiting');
        $this->assertSame(now()->getTimestamp(), Carbon::parse($this->team('lobby', method: 'GET')->json('data.waiting_since'))->getTimestamp());

        $this->team('admit')->assertOk()->assertJsonPath('data.state', 'admitted');
        Event::assertDispatched(LobbyChangedBroadcast::class, fn (LobbyChangedBroadcast $event): bool => $event->state === 'admitted');

        $this->mine('lobby', 'GET')->assertOk()->assertJsonPath('data.state', 'admitted');
        $joined = $this->mine('join')->assertOk()->assertJsonPath('data.status', 'ready')->assertHeader('Cache-Control', 'no-store, private')->json('data.credentials');
        $this->assertSame($this->mine('join')->json('data.credentials.token'), $joined['token']);
        $this->assertSame($this->recruiterId, (int) $this->roomRow()->admitted_by);

        // Admitting again is harmless and tells nobody again.
        $this->team('admit')->assertOk()->assertJsonPath('data.state', 'admitted');
        Event::assertDispatchedTimes(LobbyChangedBroadcast::class, 2);
    }

    public function test_the_candidate_counts_as_waiting_for_a_minute_and_a_poll_keeps_their_place(): void
    {
        $this->confirmedVideoInterview();

        $this->team('admit')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:candidate-not-waiting');
        $this->mine('join')->assertOk();

        Carbon::setTestNow(now()->addSeconds(61));
        $this->team('lobby', method: 'GET')->assertOk()->assertJsonPath('data.state', 'idle');
        $this->team('admit')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:candidate-not-waiting');

        $this->mine('lobby', 'GET')->assertOk()->assertJsonPath('data.state', 'waiting');
        $this->team('lobby', method: 'GET')->assertOk()->assertJsonPath('data.state', 'waiting');

        Carbon::setTestNow(now()->addSeconds(30));
        $this->team('admit')->assertOk()->assertJsonPath('data.state', 'admitted');
    }

    public function test_only_the_applicant_of_the_application_enters_the_lobby(): void
    {
        $this->confirmedVideoInterview();

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/video/join", [], $this->tokenFor($this->seeker('other@example.test')))->assertNotFound();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/video/join", [], $this->recruiterToken)->assertForbidden();
    }

    public function test_the_lobby_channel_is_open_to_the_panel_and_the_candidate_only(): void
    {
        $this->confirmedVideoInterview(['participant_ids' => [$this->adminId]]);
        $interviewId = (int) $this->roomRow()->id;
        $authorizer = $this->app->make(VideoChannelAuthorizer::class);

        $this->assertTrue($authorizer->mayListen($this->member($this->recruiterId, 'recruiter'), $interviewId));
        $this->assertTrue($authorizer->mayListen($this->member($this->adminId, 'company_admin'), $interviewId));
        $this->assertTrue($authorizer->mayListen(Actor::jobSeeker($this->seekerId), $interviewId));
        $this->assertFalse($authorizer->mayListen($this->member($this->secondRecruiterId, 'recruiter'), $interviewId));
        $this->assertFalse($authorizer->mayListen(Actor::companyMember($this->otherAdminId)->withMembership(new CompanyMembership($this->otherCompanyId, 'company_admin')), $interviewId));
        $this->assertFalse($authorizer->mayListen(Actor::jobSeeker($this->seeker('other@example.test')), $interviewId));
        $this->assertFalse($authorizer->mayListen(Actor::jobSeeker($this->seekerId), $interviewId + 999));
    }

    // ----- closing the rooms -----

    public function test_a_room_is_closed_and_its_tokens_removed_after_the_join_window(): void
    {
        $this->confirmedVideoInterview(['participant_ids' => [$this->adminId]]);
        $close = $this->app->make(CloseVideoRooms::class);
        $this->team('join')->assertOk();
        $this->team('join', $this->adminToken)->assertOk();

        $this->assertSame(0, $close->execute());

        Carbon::setTestNow(now()->addMinutes(5 + 60 + 31));
        $this->assertSame(1, $close->execute());
        $this->assertSame(0, $close->execute());

        $this->assertCount(1, $this->sentToRoom('destroy'));
        $this->assertCount(2, $this->sent('remove_token'));
        $this->assertNotNull($this->roomRow()->video_room_closed_at);
        $this->assertSame(0, DB::table('recruitment_interview_video_tokens')->count());
    }

    public function test_a_cancelled_interview_closes_its_room_at_once(): void
    {
        $this->confirmedVideoInterview();
        $this->team('join')->assertOk();

        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();

        $this->artisan('recruitment:close-video-rooms')->expectsOutput('Closed 1 video room.')->assertSuccessful();
        $this->assertNotNull($this->roomRow()->video_room_closed_at);
    }

    public function test_a_room_that_cannot_be_closed_stays_open_for_the_next_run(): void
    {
        $this->confirmedVideoInterview();
        $this->team('join')->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();
        $close = $this->app->make(CloseVideoRooms::class);

        $this->janusDown = true;
        $this->assertSame(0, $close->execute());
        $this->assertNull($this->roomRow()->video_room_closed_at);
        $this->assertSame(1, DB::table('recruitment_interview_video_tokens')->count());

        $this->janusDown = false;
        $this->assertSame(1, $close->execute());
        $this->assertNotNull($this->roomRow()->video_room_closed_at);
    }

    public function test_an_interview_without_a_room_needs_no_closing(): void
    {
        $this->confirmedVideoInterview();
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();

        $this->assertSame(0, $this->app->make(CloseVideoRooms::class)->execute());
    }
}
