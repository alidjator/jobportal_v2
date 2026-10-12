<?php

namespace Src\Recruitment\Infrastructure\Voice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Src\Recruitment\Application\Voice\VoiceGateway;
use Src\Recruitment\Application\Voice\VoiceNames;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * Talks to a self-hosted Asterisk through its ARI (REST). The bridge, the dialled channel and the channels of the team's browsers are ARI resources; the
 * temporary browser accounts are PJSIP objects written through the ARI dynamic configuration (`/asterisk/config/dynamic/res_pjsip/...`), so no file is edited.
 * A resource that is already gone counts as removed. Anything else that is not a success is logged and answered as 503, without the server's text.
 */
final readonly class AsteriskAriGateway implements VoiceGateway
{
    public const string VOICE_UNAVAILABLE = 'voice-server-unavailable';

    private const int TIMEOUT_SECONDS = 5;

    private const int DOWNLOAD_TIMEOUT_SECONDS = 300;

    /** Seconds the phone may ring before the voice server gives up. */
    private const int RING_SECONDS = 45;

    /** The file format of a call recording and the sound (a file of the voice server's sound library, set up with it) that announces it. */
    public const string RECORDING_FORMAT = 'wav';

    public const string RECORDING_ANNOUNCEMENT = 'jobportal/recording-announcement';

    /** The dialplan context a browser account may only reach (the interview extension); it is set up with the voice server (`deploy/asterisk/`). */
    private const string TEAM_CONTEXT = 'jobportal-team';

    public function __construct(
        private Factory $http,
        private ?string $ariUrl,
        private ?string $user,
        private ?string $password,
        private ?string $wssUrl,
        private ?string $sipDomain,
        private ?string $callerId,
        private string $trunk,
    ) {}

    public function openBridge(string $bridgeId): void
    {
        $this->send('post', '/bridges/'.rawurlencode($bridgeId), ['type' => 'mixing', 'name' => $bridgeId], 'open bridge', allowed: [409]);
    }

    public function closeBridge(string $bridgeId): void
    {
        $this->send('delete', '/bridges/'.rawurlencode($bridgeId), [], 'close bridge', allowed: [404]);
    }

    public function provisionEndpoint(string $username, string $password): void
    {
        $this->config('auth', $username, ['auth_type' => 'userpass', 'username' => $username, 'password' => $password]);
        $this->config('aor', $username, ['max_contacts' => '1', 'remove_existing' => 'yes']);
        $this->config('endpoint', $username, [
            'transport' => 'transport-wss',
            'context' => self::TEAM_CONTEXT,
            'disallow' => 'all',
            'allow' => 'opus,ulaw',
            'webrtc' => 'yes',
            'direct_media' => 'no',
            'auth' => $username,
            'aors' => $username,
        ]);
    }

    public function removeEndpoint(string $username): void
    {
        foreach (['endpoint', 'aor', 'auth'] as $type) {
            $this->send('delete', "/asterisk/config/dynamic/res_pjsip/{$type}/".rawurlencode($username), [], 'remove endpoint', allowed: [404]);
        }
    }

    public function channelsInBridge(string $bridgeId): int
    {
        $response = $this->send('get', '/bridges/'.rawurlencode($bridgeId), [], 'read bridge', allowed: [404]);

        return $response->status() === 404 ? 0 : count((array) $response->json('channels', []));
    }

    public function dial(string $channelId, string $number, string $callId): void
    {
        $this->send('post', '/channels', [
            'endpoint' => 'PJSIP/'.$number.'@'.$this->trunk,
            'app' => VoiceNames::APP,
            'appArgs' => 'candidate,'.$callId,
            'channelId' => $channelId,
            'callerId' => (string) $this->required($this->callerId),
            'timeout' => self::RING_SECONDS,
        ], 'dial');
    }

    public function hangup(string $channelId): void
    {
        $this->send('delete', '/channels/'.rawurlencode($channelId), ['reason' => 'normal'], 'hang up', allowed: [404]);
    }

    public function answer(string $channelId): void
    {
        $this->send('post', '/channels/'.rawurlencode($channelId).'/answer', [], 'answer');
    }

    public function addToBridge(string $bridgeId, string $channelId): void
    {
        $this->send('post', '/bridges/'.rawurlencode($bridgeId).'/addChannel', ['channel' => $channelId], 'add to bridge');
    }

    public function limitDuration(string $channelId, int $seconds): void
    {
        $this->send('post', '/channels/'.rawurlencode($channelId).'/variable', ['variable' => 'TIMEOUT(absolute)', 'value' => (string) $seconds], 'limit duration');
    }

    public function startRecording(string $bridgeId, string $name, int $maxSeconds): void
    {
        // `ifExists=fail` so a repeated start can never overwrite a recording; a wav file plays in every browser.
        $this->send('post', '/bridges/'.rawurlencode($bridgeId).'/record', [
            'name' => $name,
            'format' => self::RECORDING_FORMAT,
            'maxDurationSeconds' => max(1, $maxSeconds),
            'ifExists' => 'fail',
            'beep' => 'false',
        ], 'start recording');
    }

    public function announceRecording(string $bridgeId): void
    {
        $this->send('post', '/bridges/'.rawurlencode($bridgeId).'/play', ['media' => 'sound:'.self::RECORDING_ANNOUNCEMENT], 'announce recording');
    }

    public function stopRecording(string $name): void
    {
        $this->send('post', '/recordings/live/'.rawurlencode($name).'/stop', [], 'stop recording', allowed: [404]);
    }

    public function fetchRecording(string $name)
    {
        $file = tmpfile();

        if ($file === false) {
            throw new ExternalServiceUnavailable('The recording cannot be fetched.', self::VOICE_UNAVAILABLE);
        }

        $response = $this->send('get', '/recordings/stored/'.rawurlencode($name).'/file', [], 'fetch recording', allowed: [404], sink: $file);

        if ($response->status() === 404) {
            fclose($file);

            return null;
        }

        rewind($file);

        return $file;
    }

    public function deleteRecording(string $name): void
    {
        $this->send('delete', '/recordings/stored/'.rawurlencode($name), [], 'delete recording', allowed: [404]);
    }

    public function sipDomain(): string
    {
        return (string) ($this->sipDomain ?: parse_url($this->required($this->wssUrl), PHP_URL_HOST));
    }

    public function websocketUrl(): string
    {
        return $this->required($this->wssUrl);
    }

    /**
     * Writes one PJSIP object (created or replaced) through the dynamic configuration.
     *
     * @param  array<string, string>  $fields
     */
    private function config(string $type, string $id, array $fields): void
    {
        $body = ['fields' => array_map(static fn (string $attribute, string $value): array => ['attribute' => $attribute, 'value' => $value], array_keys($fields), array_values($fields))];

        $this->send('put', "/asterisk/config/dynamic/res_pjsip/{$type}/".rawurlencode($id), $body, 'write endpoint', json: true);
    }

    /**
     * @param  array<string, mixed>  $data  the query string, or the JSON body when `$json` is set
     * @param  list<int>  $allowed  statuses that are not a failure for this request
     * @param  resource|null  $sink  where the body of the response is written instead of being held in memory
     *
     * @throws ExternalServiceUnavailable
     */
    private function send(string $method, string $path, array $data, string $step, array $allowed = [], bool $json = false, $sink = null): Response
    {
        $request = $this->request();

        if ($sink !== null) {
            // A finished call can be tens of megabytes, far more than the few seconds a control request gets.
            $request = $request->sink($sink)->timeout(self::DOWNLOAD_TIMEOUT_SECONDS);
        }

        try {
            // ARI takes its parameters in the query string and rejects an empty JSON body, so only the configuration calls carry a body.
            $response = $json
                ? $request->asJson()->{$method}($path, $data)
                : $request->send(strtoupper($method), $path.($data === [] ? '' : '?'.http_build_query($data)));
        } catch (ConnectionException $e) {
            throw new ExternalServiceUnavailable('The voice server is unavailable.', self::VOICE_UNAVAILABLE, $e);
        }

        if (! $response->successful() && ! in_array($response->status(), $allowed, true)) {
            Log::warning('Voice server refused a request.', ['step' => $step, 'status' => $response->status()]);

            throw new ExternalServiceUnavailable('The voice server is unavailable.', self::VOICE_UNAVAILABLE);
        }

        return $response;
    }

    /** @throws ExternalServiceUnavailable */
    private function request(): PendingRequest
    {
        return $this->http->baseUrl(rtrim($this->required($this->ariUrl), '/'))->withBasicAuth((string) $this->required($this->user), (string) $this->required($this->password))
            ->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    /** @throws ExternalServiceUnavailable */
    private function required(?string $value): string
    {
        return filled($value) ? (string) $value : throw new ExternalServiceUnavailable('The voice server is not configured.', self::VOICE_UNAVAILABLE);
    }
}
