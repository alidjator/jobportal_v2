<?php

namespace Src\Platform\Infrastructure\Portal;

use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Src\Platform\Application\Portal\PortalPreviewTokens;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalOwnerType;

/** The token is the owner and the expiry, encrypted and signed with the application key; nothing is stored. */
final readonly class EncryptedPortalPreviewTokens implements PortalPreviewTokens
{
    public function __construct(private Encrypter $encrypter, private Clock $clock) {}

    public function issue(PortalOwner $owner, DateTimeImmutable $expiresAt): string
    {
        return $this->encrypter->encryptString(json_encode(['t' => $owner->type->value, 'i' => $owner->id, 'e' => $expiresAt->getTimestamp()], JSON_THROW_ON_ERROR));
    }

    public function read(string $token): ?PortalOwner
    {
        try {
            $data = json_decode($this->encrypter->decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        $type = is_array($data) && is_string($data['t'] ?? null) ? PortalOwnerType::tryFrom($data['t']) : null;

        if ($type === null || ! is_int($data['i'] ?? null) || ! is_int($data['e'] ?? null) || $data['e'] < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return match ($type) {
            PortalOwnerType::Platform => PortalOwner::platform(),
            PortalOwnerType::Group => PortalOwner::group($data['i']),
            PortalOwnerType::Company => PortalOwner::company($data['i']),
        };
    }
}
