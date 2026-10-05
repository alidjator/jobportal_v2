<?php

namespace Src\Company\Application\Invitations;

final readonly class AcceptInvitationCommand
{
    public function __construct(
        public string $token,
        public string $name,
        public string $password,
        public string $language,
        public string $deviceName,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
