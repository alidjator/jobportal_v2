<?php

namespace Src\Recruitment\Application;

/**
 * The applicant's detail of one application, with the title of the job and the name of its company (null when either no longer exists) and the content of the job as it was when they
 * applied (`jobContent`, in the language of the request; null while the application has none).
 *
 * The checks are those of `phase-8-pre-employment-checks.md` as the candidate sees them (none while the feature is off).
 *
 * @param  array<string, mixed>|null  $jobContent
 * @param  list<Checks\MyCheck>  $checks
 */
final readonly class ApplicationView
{
    public function __construct(public ApplicationDetailData $data, public ?string $jobTitle, public ?string $companyName, public ?array $jobContent = null, public array $checks = []) {}
}
