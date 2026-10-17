<?php

namespace Src\Recruitment\Application;

/** The link to the client page of an application for the job seeker. `$template` has `{application_id}` in it; empty or null means the page is not configured, so there is no link. */
final readonly class ApplicationPageLink
{
    public function __construct(private ?string $template = null) {}

    public function to(int $applicationId): ?string
    {
        return $this->template === null || trim($this->template) === '' ? null : str_replace('{application_id}', (string) $applicationId, trim($this->template));
    }
}
