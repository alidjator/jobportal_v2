<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Infrastructure\Voice\AriEventListener;

/** A phone interview of the first application with a faked voice server (ARI): what the voice call tests of the tele-interview form 3 share. */
trait DrivesVoiceInterviews
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    /** How many channels the fake bridge holds. */
    private int $bridgeChannels = 1;

    /** The fake voice server answers 503 to everything while this is set. */
    private bool $ariDown = false;

    /** The file the fake voice server holds for a finished recording; null while it has none (the file appears a moment after a stop). */
    private ?string $recordingFile = 'RIFF-wav-data';

    /** The fake voice server refuses to play the announcement while this is set. */
    private bool $announceFails = false;

    /** The fake voice server refuses to originate a channel while this is set. */
    private bool $dialRefused = false;

    /** Builds the fixtures, applies for a job, switches phone interviews on and fakes the voice server. */
    protected function setUpVoiceInterviews(): void
    {
        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VOICE_ENABLED, true, null);
        $this->fakeAsterisk();
    }

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
                    $this->announceFails && $request->method() === 'POST' && str_ends_with($path, '/play') => Http::response('', 500),
                    $this->dialRefused && $request->method() === 'POST' && $path === '/ari/channels' => Http::response('', 500),
                    $request->method() === 'GET' && str_starts_with($path, '/ari/recordings/stored/') => $this->recordingFile === null ? Http::response('', 404) : Http::response($this->recordingFile),
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
}
