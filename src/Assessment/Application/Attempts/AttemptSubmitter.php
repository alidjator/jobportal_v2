<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Application\Reports\ReportDrafter;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Application\Settings\Settings;

/** Closes an attempt: stamps it, marks the invitation submitted, scores the answers, sets the day the video files are purged and drafts the report of a psychological test. Runs inside the caller's transaction. */
final readonly class AttemptSubmitter
{
    public function __construct(
        private AttemptRepository $attempts,
        private InvitationRepository $invitations,
        private ScoreAttempt $scoring,
        private ReportDrafter $drafts,
        private VideoTakeRepository $videoTakes,
        private Settings $settings,
    ) {}

    public function submit(AttemptContext $context, bool $auto): SubmittedAttempt
    {
        $attempt = $context->attempt->submit($context->now, $auto);
        $invitation = $context->invitation->submit($attempt->submittedAt ?? $context->now);

        $this->attempts->update($attempt);
        $this->videoTakes->scheduleExpiry((int) $attempt->id, ($attempt->submittedAt ?? $context->now)->modify('+'.$this->settings->integer(AssessmentSettings::VIDEO_RETENTION_DAYS).' days'));
        $this->invitations->update($invitation);
        $result = $this->scoring->execute($attempt, $invitation, $context->test, $context->sections);
        $this->drafts->draft($attempt, $invitation, $context->test, $result);

        return new SubmittedAttempt($attempt, new AttemptSubmitted(
            (int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId, $auto, $context->now,
        ));
    }
}
