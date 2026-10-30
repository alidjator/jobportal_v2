<?php

namespace Src\JobSeeker\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ResumeImports;

/** Adds a PDF to a job seeker's resumes for another context: the same checks as an upload by the job seeker (the limit of 5, the size and the content). */
final readonly class ImportResume implements ResumeImports
{
    public function __construct(private UploadMyResume $upload) {}

    public function import(int $userId, string $name, string $contents): int
    {
        return (int) $this->upload->execute(Actor::jobSeeker($userId), $contents, $name)->id;
    }
}
