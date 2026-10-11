<?php

namespace Src\Assessment\Application\Invitations;

use DateTimeImmutable;

final readonly class InvitePsychologistCommand
{
    public function __construct(
        public int $providerId,
        public string $email,
        public string $name,
        public string $strNumber,
        public DateTimeImmutable $strValidUntil,
        public string $silpNumber,
        public DateTimeImmutable $silpValidUntil,
        public string $language,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
