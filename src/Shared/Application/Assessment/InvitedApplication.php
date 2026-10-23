<?php

namespace Src\Shared\Application\Assessment;

/** The application a test is sent for, as far as Assessment needs to know it. `stageId` is the assessment stage the application is in when the test is sent with its move. */
final readonly class InvitedApplication
{
    public function __construct(public int $applicationId, public int $companyId, public int $jobId, public int $applicantUserId, public ?int $stageId = null) {}
}
