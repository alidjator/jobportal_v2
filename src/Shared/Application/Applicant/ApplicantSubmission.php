<?php

namespace Src\Shared\Application\Applicant;

/**
 * What an application takes from the applicant's profile at the moment of applying.
 *
 * `missing` holds the codes of the profile parts still needed to apply (`phone`, `location`, `history`, `resume`); `resume` is the chosen resume, or null
 * when the applicant has no such resume; `snapshot` is the profile as the recruiter will see it (without the account's name and email, which Identity owns).
 */
final readonly class ApplicantSubmission
{
    /**
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(public array $missing, public ?ApplicantResume $resume, public array $snapshot) {}
}
