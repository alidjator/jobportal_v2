<?php

namespace Src\JobSeeker\Application;

/** A stored resume ready to be sent: its bytes and the name the owner gave it. */
final readonly class ResumeFile
{
    public function __construct(public string $contents, public string $name) {}
}
