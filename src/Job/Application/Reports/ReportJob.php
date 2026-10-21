<?php

namespace Src\Job\Application\Reports;

use Src\Job\Application\JobSettings;
use Src\Job\Application\JobVisibility;
use Src\Job\Application\Saved\SavedJobAccess;
use Src\Job\Domain\Events\JobHeld;
use Src\Job\Domain\JobReportReason;
use Src\Job\Domain\JobReportRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A job seeker with a verified email reports a live job (one the public page shows). One open report per reporter and job. When the open reports come from at
 * least `jobs.report_hold_threshold` different accounts (0 turns holds off) the job goes on hold: it leaves the public view until an admin decides. The job row is
 * locked, so two reports at once count each other.
 */
final readonly class ReportJob
{
    public const int NOTE_MAX = 1000;

    public function __construct(
        private SavedJobAccess $access,
        private ApplicantContacts $contacts,
        private JobRepository $jobs,
        private JobVisibility $visibility,
        private PublicCompanies $companies,
        private JobReportRepository $reports,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only` or `email-not-verified`
     * @throws EntityNotFound the job is not live, or does not exist
     * @throws StateConflict `already-reported`
     * @throws BusinessRuleViolation `invalid-report` with `errors` per field
     */
    public function execute(Actor $actor, int $jobId, JobReportReason $reason, ?string $note): JobReportReceipt
    {
        $userId = $this->access->jobSeeker($actor);

        if ($this->contacts->contact($userId)?->emailVerified !== true) {
            throw new AccessDenied('Verify your email address before you report a job.', 'email-not-verified');
        }

        $note = $note === null || trim($note) === '' ? null : trim($note);
        $this->assertNote($reason, $note);

        $now = $this->clock->now();

        [$receipt, $held] = $this->transactions->run(function () use ($userId, $jobId, $reason, $note, $now): array {
            $this->jobs->lock($jobId);
            $job = $this->jobs->find($jobId);

            if ($job === null || ! $this->visibility->isLive($job) || $this->companies->card($job->companyId) === null) {
                throw EntityNotFound::of('Job', $jobId);
            }

            if ($this->reports->hasOpenReport($jobId, $userId)) {
                throw new StateConflict('You have already reported this job.', 'already-reported');
            }

            $id = $this->reports->add($jobId, $userId, $reason, $note, $now);
            $threshold = $this->settings->integer(JobSettings::REPORT_HOLD_THRESHOLD);
            $reporters = $this->reports->distinctOpenReporters($jobId);
            $held = null;

            if ($threshold > 0 && $reporters >= $threshold && ! $job->isOnHold()) {
                $this->jobs->save($job->hold($now));
                $held = new JobHeld($jobId, $job->companyId, $reporters, $now);
            }

            return [new JobReportReceipt($id, $jobId, $reason, $now), $held];
        });

        if ($held !== null) {
            $this->events->dispatch([$held]);
        }

        return $receipt;
    }

    /** @throws BusinessRuleViolation `invalid-report` */
    private function assertNote(JobReportReason $reason, ?string $note): void
    {
        if ($reason === JobReportReason::Other && $note === null) {
            throw new BusinessRuleViolation('Tell us what is wrong with this job.', 'invalid-report', extensions: ['errors' => ['note' => ['Tell us what is wrong with this job.']]]);
        }

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            $message = 'The note can have at most :max characters.';

            throw new BusinessRuleViolation($message, 'invalid-report', extensions: ['errors' => ['note' => [$message]]], parameters: ['max' => self::NOTE_MAX]);
        }
    }
}
