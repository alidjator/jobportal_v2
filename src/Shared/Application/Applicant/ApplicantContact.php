<?php

namespace Src\Shared\Application\Applicant;

/** The name, email and language of an applicant's account, and whether the account is active (not suspended or deactivated) and the email was verified. */
final readonly class ApplicantContact
{
    public function __construct(public string $name, public string $email, public string $locale, public bool $active, public bool $emailVerified) {}
}
