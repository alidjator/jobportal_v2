<?php

namespace Src\Shared\Application\Applicant;

/** One of the applicant's resumes as a file: the name they gave it and its bytes. */
final readonly class ApplicantResume
{
    public function __construct(public string $name, public string $contents) {}
}
