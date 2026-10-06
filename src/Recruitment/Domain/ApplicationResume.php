<?php

namespace Src\Recruitment\Domain;

/** The application's own copy of the resume file: a random path on the private disk and the name shown to the recruiter. */
final readonly class ApplicationResume
{
    public function __construct(
        public string $name,
        public string $path,
        public int $sizeBytes,
    ) {}
}
