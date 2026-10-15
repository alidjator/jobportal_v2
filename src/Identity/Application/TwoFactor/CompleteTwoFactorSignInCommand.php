<?php

namespace Src\Identity\Application\TwoFactor;

final readonly class CompleteTwoFactorSignInCommand
{
    public function __construct(
        public string $challengeToken,
        public ?string $code,
        public ?string $recoveryCode,
        public string $deviceName,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
