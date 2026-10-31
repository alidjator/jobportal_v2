<?php

namespace Src\Shared\Application\Tasks;

use DateTimeImmutable;

/**
 * One thing that waits for a job seeker (`docs/design/phase-8-candidate-experience.md` section 3), named by the context that owns it. `subjectId` is the id of the thing itself (the interview, the offer, the test
 * invitation, the job invitation or the check); `dueAt` is when it stops being possible, null when it never does; `createdAt` orders the items that have no due time.
 */
final readonly class ApplicantTask
{
    public const string INTERVIEW_CONFIRM = 'interview_confirm';

    public const string OFFER_ANSWER = 'offer_answer';

    public const string ASSESSMENT_TAKE = 'assessment_take';

    public const string JOB_INVITATION = 'job_invitation';

    public const string CHECK_CONSENT = 'check_consent';

    public function __construct(
        public string $key,
        public int $subjectId,
        public ?int $applicationId,
        public int $jobId,
        public int $companyId,
        public ?DateTimeImmutable $dueAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
