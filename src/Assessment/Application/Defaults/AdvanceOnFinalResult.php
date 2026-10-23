<?php

namespace Src\Assessment\Application\Defaults;

use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Domain\Events\ResultFinalized;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Assessment\Domain\JobStageTestRepository;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Recruitment\ApplicationAdvancer;

/**
 * Moves an application on by itself when its skill test is final with a percentage at or above the threshold of the job's default test for the stage the test was sent in
 * (`docs/design/phase-8-assessment-automation.md` section 2). Nothing happens when the result is below the threshold, when the integrity tier is `major` (a person looks first), when the
 * test was not sent with a move into a stage that has such a rule, or when the application has left that stage; Recruitment decides the last two when it moves. Running again
 * for the same result does nothing more, because the application is no longer in the stage.
 */
final readonly class AdvanceOnFinalResult
{
    public function __construct(
        private InvitationRepository $invitations,
        private JobStageTestRepository $defaults,
        private ApplicationAdvancer $advancer,
        private InAppNotifier $notifier,
        private JobDirectory $jobs,
    ) {}

    public function handle(ResultFinalized $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);

        if (! $invitation instanceof Invitation || $invitation->status !== InvitationStatus::Submitted || $invitation->stageId === null) {
            return;
        }

        $default = $this->defaults->find($invitation->jobId, $invitation->stageId);

        if ($default === null || ! $default->advances() || $event->percentage < (int) $default->advanceMinPercentage || $event->integrityTier === IntegrityTier::Major->value) {
            return;
        }

        $note = sprintf('Otomatis: skor %s%% ≥ %d%%', $this->percent($event->percentage), (int) $default->advanceMinPercentage);

        if (! $this->advancer->advance($invitation->applicationId, $invitation->stageId, (int) $default->advanceToStageId, $note, $invitation->invitedByUserId)) {
            return;
        }

        $this->notifier->send(array_values(array_unique([...$this->jobs->assigneeUserIds($invitation->jobId), $invitation->invitedByUserId])), AssessmentNotificationType::AUTO_ADVANCED, [
            'application_id' => $invitation->applicationId,
            'job_id' => $invitation->jobId,
            'from_stage_id' => $invitation->stageId,
            'to_stage_id' => (int) $default->advanceToStageId,
            'percentage' => $event->percentage,
        ]);
    }

    /** 85 for 85.00, 85.5 for 85.50 and 85.25 for 85.25. */
    private function percent(float $percentage): string
    {
        return rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');
    }
}
