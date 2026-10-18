<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Applicant\GeneratedCvSpec;

/**
 * What a job seeker sends when applying: the resume to attach or, instead, a CV to generate from the profile (exactly one of the two), an optional cover
 * letter and the raw answers by question id. `source` is only a hint of where the applicant came from; the server decides what is recorded. `talentPoolConsent` is the
 * optional, unticked-by-default consent to be kept in the company's talent pool for other jobs (`docs/design/phase-8-talent-pool.md` section 1).
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
        public bool $talentPoolConsent = false,
    ) {}
}
