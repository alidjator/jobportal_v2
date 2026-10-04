<?php

namespace Src\Identity\Application\Auth;

/** The verified claims of a Google ID token. `subject` is Google's stable account id; the email can change, the subject cannot. */
final readonly class GoogleIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
