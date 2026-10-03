<?php

namespace Src\Identity\Domain;

final readonly class VerifiedSocialIdentity
{
    public function __construct(
        public string $uid,
        public ?string $name,
        public ?string $email,
    ) {}
}
