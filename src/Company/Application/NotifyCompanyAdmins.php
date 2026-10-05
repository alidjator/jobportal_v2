<?php

namespace Src\Company\Application;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Notifications\InAppNotifier;

/** Writes one in-app notification for every company admin of a company. A company that has none (all removed) simply gets nothing. */
final readonly class NotifyCompanyAdmins
{
    public function __construct(
        private CompanyMemberRepository $members,
        private InAppNotifier $notifier,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function execute(int $companyId, string $type, array $data = []): void
    {
        $adminIds = [];

        foreach ($this->members->ofCompany($companyId) as $member) {
            if ($member->role === CompanyRole::CompanyAdmin) {
                $adminIds[] = $member->userId;
            }
        }

        $this->notifier->send($adminIds, $type, $data);
    }
}
