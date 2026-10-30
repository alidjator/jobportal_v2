<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Domain\CheckType;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Changes the name, the instructions and the consent of a check type, or deactivates and reactivates it (`$active`). The kind never changes. */
final readonly class UpdateCheckType
{
    public function __construct(
        private ChecksAccess $access,
        private CheckTypeRepository $types,
        private CandidateMessageRules $messageRules,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-check-type` or `message-link-not-allowed`
     * @throws StateConflict `check-type-name-taken`
     */
    public function execute(Actor $actor, int $id, string $name, ?string $instructions, bool $requiresConsent): CheckType
    {
        $member = $this->access->member($actor);
        $this->messageRules->assertAllowed($member->companyId, $instructions, 'instructions');

        return $this->transactions->run(function () use ($member, $id, $name, $instructions, $requiresConsent): CheckType {
            $changed = ($this->types->find($member->companyId, $id, forUpdate: true) ?? throw EntityNotFound::of('Check type', $id))->changed($name, $instructions, $requiresConsent, $this->clock->now());
            $this->types->save($changed);

            return $changed;
        });
    }

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function setActive(Actor $actor, int $id, bool $active): CheckType
    {
        $member = $this->access->member($actor);

        return $this->transactions->run(function () use ($member, $id, $active): CheckType {
            $changed = ($this->types->find($member->companyId, $id, forUpdate: true) ?? throw EntityNotFound::of('Check type', $id))->withActive($active, $this->clock->now());
            $this->types->save($changed);

            return $changed;
        });
    }
}
