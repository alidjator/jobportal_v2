<?php

namespace Src\Recruitment\Application\Checks;

use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckStatus;
use Src\Recruitment\Domain\Events\CheckChange;
use Src\Recruitment\Domain\Events\CheckChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The team changes the schedule, the place, the vendor or the note of an open check, or waives or cancels it (`$closing`). The job seeker is told when a time is set or moved and when a check is cancelled;
 * a changed place, vendor or note, and a waiver, are not announced.
 */
final readonly class UpdateCheck
{
    public function __construct(
        private ChecksAccess $access,
        private ApplicationCheckRepository $checks,
        private CandidateMessageRules $messageRules,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-check` or `message-link-not-allowed`
     * @throws StateConflict `check-closed`
     */
    public function execute(Actor $actor, int $applicationId, int $checkId, ?DateTimeImmutable $scheduledAt, ?string $place, ?string $vendor, ?string $noteToCandidate): ApplicationCheck
    {
        $result = $this->transactions->run(function () use ($actor, $applicationId, $checkId, $scheduledAt, $place, $vendor, $noteToCandidate): array {
            $team = $this->access->team($actor, $applicationId, manage: true, forUpdate: true);
            $this->messageRules->assertAllowed($team->member->companyId, $noteToCandidate, 'note_to_candidate');

            $now = $this->clock->now();
            $previous = $this->find($applicationId, $checkId);
            $changed = $previous->changed($scheduledAt, $place, $vendor, $noteToCandidate, $now);
            $this->checks->save($changed);

            $change = match (true) {
                $changed->scheduledAt === null || $changed->scheduledAt == $previous->scheduledAt => null,
                $previous->scheduledAt === null => CheckChange::Scheduled,
                default => CheckChange::Rescheduled,
            };

            return [$changed, $change === null ? null : CheckChanged::of($change, $team->application, $changed, $now)];
        });

        if ($result[1] instanceof CheckChanged) {
            $this->events->dispatch([$result[1]]);
        }

        return $result[0];
    }

    /**
     * @param  CheckStatus  $closing  `waived` or `cancelled`
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `check-closed`
     */
    public function close(Actor $actor, int $applicationId, int $checkId, CheckStatus $closing): ApplicationCheck
    {
        $result = $this->transactions->run(function () use ($actor, $applicationId, $checkId, $closing): array {
            $team = $this->access->team($actor, $applicationId, manage: true, forUpdate: true);

            $now = $this->clock->now();
            $check = $this->find($applicationId, $checkId);
            $closed = $closing === CheckStatus::Waived ? $check->waived($now) : $check->cancelled($now);
            $this->checks->save($closed);

            return [$closed, $closing === CheckStatus::Cancelled ? CheckChanged::of(CheckChange::Cancelled, $team->application, $closed, $now) : null];
        });

        if ($result[1] instanceof CheckChanged) {
            $this->events->dispatch([$result[1]]);
        }

        return $result[0];
    }

    /** @throws EntityNotFound */
    private function find(int $applicationId, int $checkId): ApplicationCheck
    {
        return $this->checks->find($applicationId, $checkId, forUpdate: true) ?? throw EntityNotFound::of('Check', $checkId);
    }
}
