<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

abstract class CompanyTestCase extends FeatureTestCase
{
    /** @return array{user: UserModel, company: int, industry: int, organization: int} */
    protected function makeCompany(array $userOver = [], array $companyOver = []): array
    {
        $uid = DB::table('users')->insertGetId($userOver + [
            'name' => 'Co '.Str::random(6), 'username' => 'co'.Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(12)).'@example.test', 'role' => 'company', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $industry = DB::table('industry_types')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $org = DB::table('organization_types')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $company = DB::table('companies')->insertGetId($companyOver + [
            'user_id' => $uid, 'industry_type_id' => $industry, 'organization_type_id' => $org,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['user' => UserModel::findOrFail($uid), 'company' => $company, 'industry' => $industry, 'organization' => $org];
    }

    protected function actingAsCompany(array $companyOver = [], array $userOver = []): array
    {
        $made = $this->makeCompany($userOver, $companyOver);
        Sanctum::actingAs($made['user']);

        return $made;
    }

    /** @return array{user: UserModel, candidate: int} */
    protected function makeCandidate(array $userOver = []): array
    {
        $uid = DB::table('users')->insertGetId($userOver + [
            'name' => 'Cand '.Str::random(6), 'username' => 'ca'.Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(12)).'@example.test', 'role' => 'candidate', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cid = DB::table('candidates')->insertGetId([
            'user_id' => $uid, 'profile_mark' => 0, 'education_mark' => 0, 'experience_mark' => 0, 'resume_mark' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['user' => UserModel::findOrFail($uid), 'candidate' => $cid];
    }

    protected function makeJob(int $company, array $over = []): int
    {
        return DB::table('jobs')->insertGetId($over + [
            'company_id' => $company, 'title' => 'Job '.Str::random(5), 'slug' => Str::lower(Str::random(14)),
            'status' => 'active', 'deadline' => now()->addDays(10)->toDateString(), 'apply_on' => 'app',
            'waiting_for_edit_approval' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
