<?php

namespace Src\Company\Application\Presets;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;

/** The members who hold exactly the permissions of a preset. A preset is copied to a member, so "uses the preset" means "has the same set". */
final readonly class PresetHolders
{
    public function __construct(private CompanyMemberRepository $members) {}

    /**
     * @param  list<string>  $permissions  catalog names
     * @return list<int> the account ids of the members (suspended ones too) who hold exactly that set
     */
    public function userIds(int $companyId, array $permissions): array
    {
        $wanted = $permissions;
        sort($wanted);
        $ids = [];

        foreach ($this->members->ofCompany($companyId) as $member) {
            $held = $member->permissions;
            sort($held);

            if ($member->role === CompanyRole::Member && $held === $wanted) {
                $ids[] = $member->userId;
            }
        }

        return $ids;
    }
}
