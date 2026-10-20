<?php

namespace Src\Recruitment\Application;

/**
 * The applicant's detail of one application, with the title of the job and the name of its company (null when either no longer exists) and the content of the job as it was when they
 * applied (`jobContent`, in the language of the request; null while the application has none).
 *
 * @param  array<string, mixed>|null  $jobContent
 */
final readonly class ApplicationView
{
    public function __construct(public ApplicationDetailData $data, public ?string $jobTitle, public ?string $companyName, public ?array $jobContent = null) {}
}
