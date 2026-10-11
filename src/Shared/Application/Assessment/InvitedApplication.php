<?php

namespace Src\Shared\Application\Assessment;

/** The application a test is sent for, as far as Assessment needs to know it. */
final readonly class InvitedApplication
{
    public function __construct(public int $applicationId, public int $companyId, public int $jobId, public int $applicantUserId) {}
}
