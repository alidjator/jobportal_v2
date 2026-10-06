<?php

namespace Src\Recruitment\Application;

/** What a job seeker sends when applying: the resume to attach, an optional cover letter and the raw answers by question id. */
final readonly class ApplyToJobInput
{
    /** @param  array<int, mixed>  $answers  the value of each answered question, by question id; the type of the value follows the question */
    public function __construct(
        public int $resumeId,
        public ?string $coverLetter,
        public array $answers,
    ) {}
}
