<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Replaces the name and the whole list of questions of a kit. Interviews scheduled with it earlier keep their copy. */
final readonly class UpdateInterviewKit
{
    public function __construct(
        private RecruitmentAccess $access,
        private InterviewKitLookup $lookup,
        private InterviewKitRepository $kits,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{text: string, guidance: ?string, weight: int}>  $questions
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-kit`
     * @throws StateConflict `kit-name-taken`
     */
    public function execute(Actor $actor, int $kitId, string $name, array $questions): InterviewKit
    {
        $member = $this->access->permitted($actor, CompanyPermission::InterviewKitsManage);
        $revised = $this->lookup->find($member, $kitId)->revise($name, $questions, $member->userId, $this->clock->now());

        $this->transactions->run(fn () => $this->kits->replace($revised));

        return $this->lookup->find($member, $kitId);
    }
}
