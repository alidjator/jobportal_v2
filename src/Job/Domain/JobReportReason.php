<?php

namespace Src\Job\Domain;

/** Why a job seeker reports a job. The values match the check constraint of `job_reports.reason`; `other` needs a note. */
enum JobReportReason: string
{
    case FeeRequested = 'fee_requested';
    case FakeOrMisleading = 'fake_or_misleading';
    case Discriminatory = 'discriminatory';
    case SpamOrDuplicate = 'spam_or_duplicate';
    case Inappropriate = 'inappropriate';
    case FalseAccessibilityClaim = 'false_accessibility_claim';
    case Other = 'other';
}
