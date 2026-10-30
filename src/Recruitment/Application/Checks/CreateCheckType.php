<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Domain\CheckKind;
use Src\Recruitment\Domain\CheckType;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member with `checks.manage` defines a kind of check. A medical, background or police record check always asks for the candidate's consent. */
final readonly class CreateCheckType
{
    public function __construct(private ChecksAccess $access, private CheckTypeRepository $types, private CandidateMessageRules $messageRules, private TransactionManager $transactions, private Clock $clock) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws BusinessRuleViolation `invalid-check-type` or `message-link-not-allowed`
     * @throws StateConflict `check-type-name-taken`
     */
    public function execute(Actor $actor, string $name, CheckKind $kind, ?string $instructions, bool $requiresConsent): CheckType
    {
        $member = $this->access->member($actor);
        $this->messageRules->assertAllowed($member->companyId, $instructions, 'instructions');

        $type = CheckType::create($member->companyId, $name, $kind, $instructions, $requiresConsent, $this->clock->now());

        // In its own transaction, so a name taken (the unique index) is undone cleanly and does not abort an outer one.
        return $this->transactions->run(fn (): CheckType => $this->types->add($type));
    }
}
