<?php

namespace Src\Recruitment\Application\Checks;

use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member with `checks.manage` requests a check on an application in the interview, offer or hired stage. One open check per type and application. */
final readonly class RequestCheck
{
    public function __construct(
        private ChecksAccess $access,
        private ApplicationCheckRepository $checks,
        private CheckTypeRepository $types,
        private CandidateMessageRules $messageRules,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound the application or the check type
     * @throws BusinessRuleViolation `check-type-inactive`, `invalid-check` or `message-link-not-allowed`
     * @throws StateConflict `check-not-allowed-stage` or `check-already-open`
     */
    public function execute(Actor $actor, int $applicationId, int $checkTypeId, ?DateTimeImmutable $scheduledAt, ?string $place, ?string $vendor, ?string $noteToCandidate): ApplicationCheck
    {
        return $this->transactions->run(function () use ($actor, $applicationId, $checkTypeId, $scheduledAt, $place, $vendor, $noteToCandidate): ApplicationCheck {
            $team = $this->access->team($actor, $applicationId, manage: true, forUpdate: true);

            if (! in_array($team->application->stageType, [StageType::Interview, StageType::Offer, StageType::Hired], true)) {
                throw new StateConflict('A check can be requested once the application reaches the interview, offer or hired stage.', 'check-not-allowed-stage');
            }

            $type = $this->types->find($team->member->companyId, $checkTypeId) ?? throw EntityNotFound::of('Check type', $checkTypeId);

            if (! $type->isActive) {
                throw new BusinessRuleViolation('This check type is not in use any more.', 'check-type-inactive');
            }

            $this->messageRules->assertAllowed($team->member->companyId, $noteToCandidate, 'note_to_candidate');

            return $this->checks->add(ApplicationCheck::request($applicationId, $checkTypeId, $type->requiresConsent, $scheduledAt, $place, $vendor, $noteToCandidate, $team->member->userId, $this->clock->now()));
        });
    }
}
