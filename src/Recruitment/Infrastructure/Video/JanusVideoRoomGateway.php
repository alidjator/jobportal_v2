<?php

namespace Src\Recruitment\Infrastructure\Video;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use Src\Recruitment\Application\Video\VideoRoomGateway;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * Talks to a self-hosted Janus server. Rooms go through its HTTP API (a session and a VideoRoom handle per operation, closed at once); tokens go through its Admin
 * API, which is never public. The HTTP API itself runs with `token_auth`, so the application holds a token of its own (`api_token`) that only this class sends.
 * Janus answers 200 with an error payload for most failures, so the body is checked too.
 */
final readonly class JanusVideoRoomGateway implements VideoRoomGateway
{
    public const string VIDEO_UNAVAILABLE = 'video-server-unavailable';

    private const string PLUGIN = 'janus.plugin.videoroom';

    private const int TIMEOUT_SECONDS = 5;

    /** Janus error codes of the VideoRoom plugin. */
    private const int NO_SUCH_ROOM = 426;

    private const int ROOM_EXISTS = 427;

    private const int ROOM_ATTEMPTS = 3;

    public function __construct(
        private Factory $http,
        private ?string $httpUrl,
        private ?string $adminUrl,
        private ?string $adminSecret,
        private ?string $apiToken,
        private ?string $websocketUrl,
    ) {}

    public function createRoom(string $pin, string $secret, int $maxParticipants): int
    {
        for ($attempt = 1; $attempt <= self::ROOM_ATTEMPTS; $attempt++) {
            $roomId = random_int(10_000_000, 9_999_999_999);
            $data = $this->videoRoom([
                'request' => 'create',
                'room' => $roomId,
                'permanent' => false,
                'is_private' => true,
                'pin' => $pin,
                'secret' => $secret,
                'publishers' => $maxParticipants,
                'description' => 'interview',
            ]);

            if (($data['error_code'] ?? null) === self::ROOM_EXISTS) {
                continue;
            }

            if (isset($data['error_code']) || ($data['videoroom'] ?? null) !== 'created') {
                $this->refused('create room', $data['error_code'] ?? null);
            }

            return $roomId;
        }

        $this->refused('create room', self::ROOM_EXISTS);
    }

    public function destroyRoom(int $roomId, string $secret): void
    {
        $data = $this->videoRoom(['request' => 'destroy', 'room' => $roomId, 'secret' => $secret]);

        if (isset($data['error_code']) && $data['error_code'] !== self::NO_SUCH_ROOM) {
            $this->refused('destroy room', $data['error_code']);
        }
    }

    public function startRecording(int $roomId, string $secret, string $directory): void
    {
        $this->checked('set recording directory', $this->videoRoom(['request' => 'edit', 'room' => $roomId, 'secret' => $secret, 'new_rec_dir' => $directory]));
        $this->checked('start recording', $this->videoRoom(['request' => 'enable_recording', 'room' => $roomId, 'secret' => $secret, 'record' => true]));
    }

    public function stopRecording(int $roomId, string $secret): void
    {
        $data = $this->videoRoom(['request' => 'enable_recording', 'room' => $roomId, 'secret' => $secret, 'record' => false]);

        // A room that is gone (the video server restarted) has nothing left to stop; what was written to the volume is still converted.
        if (($data['error_code'] ?? null) !== self::NO_SUCH_ROOM) {
            $this->checked('stop recording', $data);
        }
    }

    public function addToken(string $token): void
    {
        $body = $this->admin('add_token', ['token' => $token, 'plugins' => [self::PLUGIN]]);

        if (($body['janus'] ?? null) !== 'success') {
            $this->refused('add token', $body['error']['code'] ?? null);
        }
    }

    public function removeToken(string $token): void
    {
        // An error here means the token is already gone (Janus forgets stored tokens when it restarts).
        $this->admin('remove_token', ['token' => $token]);
    }

    public function websocketUrl(): string
    {
        if (blank($this->websocketUrl)) {
            throw new ExternalServiceUnavailable('The video server is not configured.', self::VIDEO_UNAVAILABLE);
        }

        return $this->websocketUrl;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ExternalServiceUnavailable
     */
    private function checked(string $step, array $data): void
    {
        if (isset($data['error_code'])) {
            $this->refused($step, $data['error_code']);
        }
    }

    /**
     * Runs one VideoRoom request on a short session and returns the plugin's answer.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws ExternalServiceUnavailable
     */
    private function videoRoom(array $body): array
    {
        $this->assertConfigured();
        $base = rtrim((string) $this->httpUrl, '/');
        $session = $this->id($this->post($base, ['janus' => 'create']), 'open session');

        try {
            $handle = $this->id($this->post("{$base}/{$session}", ['janus' => 'attach', 'plugin' => self::PLUGIN]), 'attach plugin');
            $answer = $this->post("{$base}/{$session}/{$handle}", ['janus' => 'message', 'body' => $body]);
            $data = $answer['plugindata']['data'] ?? null;

            if (! is_array($data)) {
                $this->refused('plugin request', $answer['error']['code'] ?? null);
            }

            return $data;
        } finally {
            try {
                $this->post("{$base}/{$session}", ['janus' => 'destroy']);
            } catch (ExternalServiceUnavailable) {
                // Janus drops an idle session by itself.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function admin(string $request, array $extra): array
    {
        $this->assertConfigured();

        return $this->send((string) $this->adminUrl, ['janus' => $request, 'admin_secret' => $this->adminSecret, ...$extra]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $url, array $body): array
    {
        return $this->send($url, [...$body, 'token' => $this->apiToken]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws ExternalServiceUnavailable
     */
    private function send(string $url, array $body): array
    {
        try {
            $response = $this->http->asJson()->timeout(self::TIMEOUT_SECONDS)->post($url, [...$body, 'transaction' => bin2hex(random_bytes(6))]);
        } catch (ConnectionException $e) {
            Log::warning('Video server unreachable.', ['janus' => $body['janus'] ?? null, 'reason' => $e->getMessage()]);

            throw new ExternalServiceUnavailable('The video server is unavailable.', self::VIDEO_UNAVAILABLE, $e);
        }

        $json = $response->json();

        if ($response->failed() || ! is_array($json)) {
            Log::warning('Video server refused a call.', ['janus' => $body['janus'] ?? null, 'status' => $response->status()]);

            throw new ExternalServiceUnavailable('The video server is unavailable.', self::VIDEO_UNAVAILABLE);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $answer
     *
     * @throws ExternalServiceUnavailable
     */
    private function id(array $answer, string $step): int
    {
        $id = $answer['data']['id'] ?? null;

        if (($answer['janus'] ?? null) !== 'success' || ! is_int($id)) {
            $this->refused($step, $answer['error']['code'] ?? null);
        }

        return $id;
    }

    /** @throws ExternalServiceUnavailable */
    private function assertConfigured(): void
    {
        if (blank($this->httpUrl) || blank($this->adminUrl) || blank($this->adminSecret) || blank($this->apiToken)) {
            throw new ExternalServiceUnavailable('The video server is not configured.', self::VIDEO_UNAVAILABLE);
        }
    }

    /** @throws ExternalServiceUnavailable */
    private function refused(string $step, mixed $code): never
    {
        Log::warning('Video server refused a request.', ['step' => $step, 'code' => $code]);

        throw new ExternalServiceUnavailable('The video server is unavailable.', self::VIDEO_UNAVAILABLE);
    }
}
