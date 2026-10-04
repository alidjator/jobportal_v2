<?php

namespace Src\Identity\Application\Auth;

final readonly class RegisterJobSeekerCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $language,
        public ?string $campaignCode,
        public string $deviceName,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
