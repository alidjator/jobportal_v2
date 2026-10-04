<?php

namespace Src\Identity\Application\Auth;

final readonly class GoogleSignInCommand
{
    public function __construct(
        public string $idToken,
        public string $language,
        public ?string $campaignCode,
        public string $deviceName,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
