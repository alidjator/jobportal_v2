<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company member adds a kit to the library. */
final readonly class CreateInterviewKit
{
    public function __construct(
        private RecruitmentAccess $access,
        private InterviewKitRepository $kits,
        private CompanyDirectory $companies,
        private Settings $settings,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}>  $questions
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-kit`
     * @throws StateConflict `kit-limit-reached` or `kit-name-taken`
     */
    public function execute(Actor $actor, string $name, array $questions): InterviewKit
    {
        $member = $this->access->permitted($actor, CompanyPermission::InterviewKitsManage);
        $kit = InterviewKit::create($member->companyId, $name, $questions, $member->userId, $this->clock->now());

        $id = $this->transactions->run(function () use ($member, $kit): int {
            $this->companies->lock($member->companyId);

            if ($this->kits->countForCompany($member->companyId) >= $this->settings->integer(RecruitmentSettings::MAX_INTERVIEW_KITS, $member->companyId)) {
                throw new StateConflict('The company has reached its limit of interview kits.', 'kit-limit-reached');
            }

            return $this->kits->add($kit);
        });

        return $this->kits->find($id) ?? throw new \LogicException('The new kit was not found.');
    }
}
