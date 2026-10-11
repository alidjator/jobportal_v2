<?php

namespace Src\Assessment\Application\Invitations;

final readonly class AcceptInvitationCommand
{
    public function __construct(
        public string $token,
        public string $password,
        public string $language,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
