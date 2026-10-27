<?php

namespace Src\Assessment\Application\Licence;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Domain\Events\LicenceDecided;
use Src\Assessment\Domain\LicenceStatus;
use Src\Assessment\Domain\LicenceSubmission;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The platform admin approves a licence submission (its number and date go onto the psychologist) or rejects it with a note. A decision is final. */
final readonly class DecideLicence
{
    public function __construct(
        private LicenceSubmissionRepository $submissions,
        private PsychologistRepository $psychologists,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `licence-decided`
     */
    public function approve(Actor $actor, int $submissionId, ?string $ipAddress, ?string $userAgent): LicenceSubmission
    {
        AdminOnly::assert($actor, 'approve a licence');

        return $this->decide($actor, $submissionId, null, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `licence-decided`
     */
    public function reject(Actor $actor, int $submissionId, string $note, ?string $ipAddress, ?string $userAgent): LicenceSubmission
    {
        AdminOnly::assert($actor, 'reject a licence');

        return $this->decide($actor, $submissionId, trim($note), $ipAddress, $userAgent);
    }

    private function decide(Actor $actor, int $submissionId, ?string $rejectionNote, ?string $ipAddress, ?string $userAgent): LicenceSubmission
    {
        $now = $this->clock->now();

        [$submission, $userId] = $this->transactions->run(function () use ($actor, $submissionId, $rejectionNote, $now): array {
            $submission = $this->submissions->find($submissionId, forUpdate: true) ?? throw EntityNotFound::of('LicenceSubmission', $submissionId);
            $psychologist = $this->psychologists->findById($submission->psychologistId, forUpdate: true) ?? throw EntityNotFound::of('Psychologist', $submission->psychologistId);

            if ($rejectionNote === null) {
                $submission->approve((int) $actor->id, $now);
                $psychologist->renewLicence($submission->kind, $submission->number, $submission->validUntil);
                $this->psychologists->save($psychologist);
            } else {
                $submission->reject((int) $actor->id, $now, $rejectionNote);
            }

            return [$this->submissions->save($submission), $psychologist->userId];
        });

        $this->events->dispatch([new LicenceDecided(
            (int) $submission->id, $submission->psychologistId, $userId, $submission->kind->value, $submission->status() === LicenceStatus::Approved,
            (int) $actor->id, $ipAddress, $userAgent, $now,
        )]);

        return $submission;
    }
}
