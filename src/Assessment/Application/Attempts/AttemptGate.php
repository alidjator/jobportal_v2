<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\IntegrityEventType;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Every use case of the candidate that works on an attempt goes through here. It locks the invitation and the attempt, finds out what the clock did to them
 * (a section that closed, an attempt that ran out of time, which the server then submits) and runs the use case on the result. Another candidate's
 * invitation is a 404. A use case that writes gets `attempt-closed` (409) when the attempt is over; a read gets the closed attempt back. While the attempt is open only the
 * device that holds it is served: a request without its token gets `attempt-session-required`, and one with the token of a device that was replaced gets `attempt-moved`
 * (both 409). Starting is the one call that does not carry a token (`$checkSession` false), because it issues one.
 */
final readonly class AttemptGate
{
    public function __construct(
        private InvitationRepository $invitations,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
        private AttemptContexts $contexts,
        private AttemptSubmitter $submitter,
        private AttemptSessionToken $session,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @template T
     *
     * @param  callable(AttemptContext): T  $work
     * @return T
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-started`, `attempt-closed`, `attempt-session-required` or `attempt-moved`
     */
    public function run(Actor $actor, int $invitationId, ?string $ip, callable $work, bool $readOnly = false, bool $checkSession = true): mixed
    {
        $userId = $this->userId($actor);
        /** @var SubmittedAttempt|null $submitted */
        $submitted = null;

        $outcome = $this->transactions->run(function () use ($userId, $invitationId, $ip, $work, $readOnly, $checkSession, &$submitted): array {
            $invitation = $this->invitations->find($invitationId, forUpdate: true);

            if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $userId) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            $attempt = $this->attempts->findByInvitation($invitationId, forUpdate: true)
                ?? throw new StateConflict('This test was not started.', 'attempt-not-started');
            $now = $this->clock->now();
            $context = $this->contexts->load($invitation, $attempt, $now);
            $durations = $context->durations;

            if ($attempt->isSubmitted() || $invitation->status !== InvitationStatus::Started) {
                return [$readOnly ? $work($context) : null, ! $readOnly];
            }

            if ($checkSession) {
                $this->assertHeld($attempt);
            }

            $rolled = $attempt->rolledTo($now, $durations);
            $context = $context->withAttempt($rolled);

            if ($rolled->isOutOfTime($durations)) {
                $submitted = $this->submitter->submit($context, auto: true);

                return [$readOnly ? $work($context->withAttempt($submitted->attempt)) : null, ! $readOnly];
            }

            if ($ip !== null && $rolled->lastIp !== null && $rolled->lastIp !== $ip) {
                $this->outcomes->addEvent((int) $rolled->id, IntegrityEventType::IpChange, $now);
            }

            $seen = $rolled->seenFrom($ip ?? $rolled->lastIp);

            if ($rolled !== $attempt || $seen->lastIp !== $attempt->lastIp) {
                $this->attempts->update($seen);
            }

            return [$work($context->withAttempt($seen)), false];
        });

        if ($submitted !== null) {
            $this->events->dispatch($submitted->events());
        }

        if ($outcome[1]) {
            throw new StateConflict('This test is closed: it was submitted, ran out of time or was cancelled.', 'attempt-closed');
        }

        return $outcome[0];
    }

    /** @throws StateConflict `attempt-session-required` or `attempt-moved` */
    private function assertHeld(Attempt $attempt): void
    {
        if ($attempt->isHeldBy($this->session->hash())) {
            return;
        }

        if ($this->session->hash() === null) {
            throw new StateConflict('Send the attempt session you got when the test started in the header X-Attempt-Session.', 'attempt-session-required');
        }

        throw new StateConflict('This test is open on another device now. Start it again here to take it back.', 'attempt-moved');
    }

    private function userId(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        return $actor->id;
    }
}
