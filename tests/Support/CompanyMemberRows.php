<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Src\Company\Domain\MemberPreset;

/**
 * Inserts a company membership the way the application stores one. The role `recruiter` of the tests written before permissions existed stands for
 * a member with the Recruiter preset; a company admin gets no permission rows (it holds every permission).
 */
final class CompanyMemberRows
{
    /** @param  ?list<string>  $permissions  the permissions of a member; the Recruiter preset when null */
    public static function insert(int $companyId, int $userId, string $role = 'recruiter', ?array $permissions = null): int
    {
        $stored = $role === 'company_admin' ? 'company_admin' : 'member';

        $id = (int) DB::table('company_members')->insertGetId([
            'company_id' => $companyId, 'user_id' => $userId, 'role' => $stored, 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($stored === 'member') {
            foreach ($permissions ?? MemberPreset::Recruiter->permissionNames() as $permission) {
                DB::table('company_member_permissions')->insert(['company_member_id' => $id, 'permission' => $permission, 'created_at' => now()]);
            }
        }

        return $id;
    }
}
