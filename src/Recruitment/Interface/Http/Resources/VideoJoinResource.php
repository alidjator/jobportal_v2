<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\Video\IceServer;
use Src\Recruitment\Application\Video\VideoJoinResult;

/**
 * The answer to a join. `status` is `ready` with `credentials` (the room, its pin, a token, the `display` name the browser must use when it joins, the address of the video server and the ICE servers; all valid until
 * `expires_at`) or `waiting` without them (a job seeker in the lobby). The credentials are secrets, so the answer is never cached.
 *
 * @property VideoJoinResult $resource
 */
final class VideoJoinResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $credentials = $this->resource->credentials;

        return [
            /** @var 'ready'|'waiting' */
            'status' => $this->resource->status,
            /** @var array{room_id: int, pin: string, token: string, display: string, ws_url: string, ice_servers: list<array{urls: list<string>, username: string|null, credential: string|null}>, expires_at: string}|null */
            'credentials' => $credentials === null ? null : [
                'room_id' => $credentials->roomId,
                'pin' => $credentials->pin,
                'token' => $credentials->token,
                'display' => $credentials->display,
                'ws_url' => $credentials->websocketUrl,
                'ice_servers' => array_map(static fn (IceServer $server): array => ['urls' => $server->urls, 'username' => $server->username, 'credential' => $server->credential], $credentials->iceServers),
                'expires_at' => $credentials->expiresAt->format(DATE_ATOM),
            ],
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, private');
    }
}
