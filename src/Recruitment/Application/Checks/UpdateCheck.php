<?php

namespace Src\Recruitment\Application\Checks;

use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The team changes the schedule, the place, the vendor or the note of an open check, or waives or cancels it (`$closing`). */
final readonly class UpdateCheck
{
    public function __construct(
        private ChecksAccess $access,
        private ApplicationCheckRepository $checks,
        private CandidateMessageRules $messageRules,
        private TransactionManager $transactions,
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
        return $this->transactions->run(function () use ($actor, $applicationId, $checkId, $scheduledAt, $place, $vendor, $noteToCandidate): ApplicationCheck {
            $team = $this->access->team($actor, $applicationId, manage: true, forUpdate: true);
            $this->messageRules->assertAllowed($team->member->companyId, $noteToCandidate, 'note_to_candidate');

            $changed = $this->find($applicationId, $checkId)->changed($scheduledAt, $place, $vendor, $noteToCandidate, $this->clock->now());
            $this->checks->save($changed);

            return $changed;
        });
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
        return $this->transactions->run(function () use ($actor, $applicationId, $checkId, $closing): ApplicationCheck {
            $this->access->team($actor, $applicationId, manage: true, forUpdate: true);

            $check = $this->find($applicationId, $checkId);
            $closed = $closing === CheckStatus::Waived ? $check->waived($this->clock->now()) : $check->cancelled($this->clock->now());
            $this->checks->save($closed);

            return $closed;
        });
    }

    /** @throws EntityNotFound */
    private function find(int $applicationId, int $checkId): ApplicationCheck
    {
        return $this->checks->find($applicationId, $checkId, forUpdate: true) ?? throw EntityNotFound::of('Check', $checkId);
    }
}
