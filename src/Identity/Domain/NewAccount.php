<?php

namespace Src\Identity\Domain;

final readonly class NewAccount
{
    public function __construct(
        public string $role,
        public string $name,
        public string $email,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $firebaseUid = null,
    ) {}
}
