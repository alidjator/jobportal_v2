<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Applicant\GeneratedCvSpec;

/**
 * What a job seeker sends when applying: the resume to attach or, instead, a CV to generate from the profile (exactly one of the two), an optional cover
 * letter and the raw answers by question id. `source` is only a hint of where the applicant came from; the server decides what is recorded.
 */
final readonly class ApplyToJobInput
{
    /** @param  array<int, mixed>  $answers  the value of each answered question, by question id; the type of the value follows the question */
    public function __construct(
        public ?int $resumeId,
        public ?string $coverLetter,
        public array $answers,
        public ?GeneratedCvSpec $cv = null,
        public ?string $source = null,
    ) {}
}
