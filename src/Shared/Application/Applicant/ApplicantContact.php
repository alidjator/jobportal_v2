<?php

namespace Src\Shared\Application\Applicant;

/** The name and email an application carries for its applicant. */
final readonly class ApplicantContact
{
    public function __construct(public string $name, public string $email) {}
}
