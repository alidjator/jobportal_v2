<?php

namespace Src\Recruitment\Infrastructure\Video;

use DateTimeImmutable;
use Src\Recruitment\Application\Video\IceServer;
use Src\Recruitment\Application\Video\IceServerProvider;

/**
 * The coturn server of this platform with time-limited credentials (its `use-auth-secret` mode): the user name is the expiry time and the user id, and the
 * credential is the HMAC-SHA1 of it with the shared secret. Without a configured server the browser gets none and tries a direct path.
 */
final readonly class CoturnIceServers implements IceServerProvider
{
    /** @param  list<string>  $urls */
    public function __construct(private array $urls, private ?string $secret) {}

    public function forUser(int $userId, DateTimeImmutable $expiresAt): array
    {
        if ($this->urls === [] || blank($this->secret)) {
            return [];
        }

        $username = $expiresAt->getTimestamp().':'.$userId;

        return [new IceServer($this->urls, $username, base64_encode(hash_hmac('sha1', $username, (string) $this->secret, true)))];
    }
}
