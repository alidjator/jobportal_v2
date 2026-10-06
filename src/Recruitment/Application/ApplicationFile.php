<?php

namespace Src\Recruitment\Application;

/** The application's own copy of the resume, ready to be sent: its bytes and the name it had when the applicant applied. */
final readonly class ApplicationFile
{
    public function __construct(public string $contents, public string $name) {}
}
