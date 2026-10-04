<?php

namespace Src\Identity\Application\Auth;

final readonly class SignInCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
