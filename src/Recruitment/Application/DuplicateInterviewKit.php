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
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company member copies a kit of the library under a free name. The job defaults that name the original are not copied. */
final readonly class DuplicateInterviewKit
{
    public function __construct(
        private RecruitmentAccess $access,
        private InterviewKitLookup $lookup,
        private InterviewKitRepository $kits,
        private CompanyDirectory $companies,
        private Settings $settings,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `kit-limit-reached` or `kit-name-taken`
     */
    public function execute(Actor $actor, int $kitId): InterviewKit
    {
        $member = $this->access->permitted($actor, CompanyPermission::InterviewKitsManage);
        $original = $this->lookup->find($member, $kitId);

        $id = $this->transactions->run(function () use ($member, $original): int {
            $this->companies->lock($member->companyId);

            $kits = $this->kits->forCompany($member->companyId);

            if (count($kits) >= $this->settings->integer(RecruitmentSettings::MAX_INTERVIEW_KITS, $member->companyId)) {
                throw new StateConflict('The company has reached its limit of interview kits.', 'kit-limit-reached');
            }

            $names = array_map(static fn (InterviewKit $kit): string => $kit->name, $kits);

            return $this->kits->add($original->duplicate($names, $member->userId, $this->clock->now()));
        });

        return $this->lookup->find($member, $id);
    }
}
