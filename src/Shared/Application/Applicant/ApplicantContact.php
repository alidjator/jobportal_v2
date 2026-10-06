<?php

namespace Src\Shared\Application\Applicant;

/** The name, email and language of an applicant's account. */
final readonly class ApplicantContact
{
    public function __construct(public string $name, public string $email, public string $locale) {}
}
