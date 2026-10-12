<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\Video\IceServer;
use Src\Recruitment\Application\Voice\VoiceCredentials;

/**
 * What a team member's browser needs to join the phone interview: the SIP account (`username`, `password`) and `sip_domain` to register with at `ws_url`, the
 * `extension` to dial once registered, and the ICE servers. All valid until `expires_at`. The credentials are secrets, so the answer is never cached.
 *
 * @property VoiceCredentials $resource
 */
final class VoiceJoinResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $credentials = $this->resource;

        return [
            'username' => $credentials->username,
            'password' => $credentials->password,
            'sip_domain' => $credentials->sipDomain,
            'ws_url' => $credentials->websocketUrl,
            'extension' => $credentials->extension,
            'ice_servers' => array_map(static fn (IceServer $server): array => ['urls' => $server->urls, 'username' => $server->username, 'credential' => $server->credential], $credentials->iceServers),
            'expires_at' => $credentials->expiresAt->format(DATE_ATOM),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, private');
    }
}
