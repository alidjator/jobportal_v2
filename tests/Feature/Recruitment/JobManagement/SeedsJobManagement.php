<?php

namespace Tests\Feature\Recruitment\JobManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Persistence\UserModel;

/** Data uji untuk fitur lowongan sisi perusahaan (DB test kosong; semua nilai acak agar aman paralel). */
trait SeedsJobManagement
{
    /** @var array<string, int> */
    protected array $master = [];

    protected int $companyId;

    protected int $userId;

    protected function settings(array $overrides = []): void
    {
        DB::table('settings')->insert($overrides + ['job_auto_approved' => 0, 'edited_job_auto_approved' => 1]);
    }

    protected function makeCompanyUser(string $role = 'company'): UserModel
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Acme '.Str::random(5),
            'username' => 'acme_'.Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => 'x',
            'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($role === 'company') {
            $industry = DB::table('industry_types')->insertGetId(['created_at' => now()]);
            $org = DB::table('organization_types')->insertGetId(['created_at' => now()]);
            $this->companyId = DB::table('companies')->insertGetId([
                'user_id' => $userId, 'industry_type_id' => $industry, 'organization_type_id' => $org,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->userId = $userId;

        return UserModel::query()->findOrFail($userId);
    }

    protected function actingAsCompany(): UserModel
    {
        $user = $this->makeCompanyUser();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeUserPlan(int $companyId, int $jobs = 5, int $featured = 2, int $highlight = 2): int
    {
        $planId = DB::table('plans')->insertGetId([
            'label' => 'p'.Str::random(6), 'price' => 0, 'job_limit' => $jobs,
            'featured_job_limit' => $featured, 'highlight_job_limit' => $highlight,
        ]);

        return DB::table('user_plans')->insertGetId([
            'company_id' => $companyId, 'plan_id' => $planId, 'job_limit' => $jobs,
            'featured_job_limit' => $featured, 'highlight_job_limit' => $highlight,
        ]);
    }

    protected function seedMaster(): array
    {
        $now = now();
        $bps = fn () => random_int(100_000_000, 900_000_000);

        $category = DB::table('job_categories')->insertGetId(['slug' => 'c-'.Str::random(8)]);
        DB::table('job_category_translations')->insert(['job_category_id' => $category, 'name' => 'Engineering', 'locale' => 'en']);
        $role = DB::table('job_roles')->insertGetId(['slug' => 'r-'.Str::random(8)]);
        DB::table('job_role_translations')->insert(['job_role_id' => $role, 'name' => 'Developer', 'locale' => 'en']);
        $experience = DB::table('experiences')->insertGetId(['slug' => 'e-'.Str::random(8)]);
        DB::table('experience_translations')->insert(['experience_id' => $experience, 'name' => 'Senior', 'locale' => 'en']);
        $education = DB::table('education')->insertGetId(['slug' => 'ed-'.Str::random(8)]);
        DB::table('education_translations')->insert(['education_id' => $education, 'name' => 'Bachelor', 'locale' => 'en']);
        $type = DB::table('job_types')->insertGetId(['slug' => 't-'.Str::random(8)]);
        DB::table('job_type_translations')->insert(['job_type_id' => $type, 'name' => 'Full Time', 'locale' => 'en']);
        $salaryType = DB::table('salary_types')->insertGetId(['slug' => 's-'.Str::random(8)]);
        DB::table('salary_type_translations')->insert(['salary_type_id' => $salaryType, 'name' => 'Monthly', 'locale' => 'en']);
        $workTime = DB::table('job_work_times')->insertGetId(['slug' => 'w-'.Str::random(8)]);
        $level = DB::table('job_levels')->insertGetId(['slug' => 'l-'.Str::random(8)]);
        $country = DB::table('countries')->insertGetId(['name' => 'indonesia', 'status' => 1]);
        $state = $bps();
        DB::table('states')->insert(['name' => 'Jawa Test', 'country_id' => $country, 'bps_code' => $state]);
        $city = $bps();
        DB::table('cities')->insert(['name' => 'kota bandung', 'bps_code' => $city, 'state_bps_code' => $state]);
        $subdistrict = $bps();
        DB::table('subdistricts')->insert(['name' => 'coblong', 'bps_code' => $subdistrict, 'city_bps_code' => $city]);

        return $this->master = compact(
            'category', 'role', 'experience', 'education', 'type', 'salaryType', 'workTime', 'level', 'country', 'state', 'city', 'subdistrict',
        );
    }

    /** @return array<string, mixed> */
    protected function jobPayload(array $override = []): array
    {
        $m = $this->master ?: $this->seedMaster();

        return array_merge([
            'title' => 'Backend Engineer '.Str::random(8),
            'category_id' => $m['category'],
            'role_id' => $m['role'],
            'experience' => $m['experience'],
            'education' => $m['education'],
            'job_type' => $m['type'],
            'vacancies' => '3',
            'salary_mode' => 'range',
            'min_salary' => 5000000,
            'max_salary' => 9000000,
            'salary_type' => $m['salaryType'],
            'show_salary' => '1',
            'deadline' => now()->addDays(20)->format('Y-m-d'),
            'description' => '<p>Build things</p>',
            'apply_on' => 'app',
            'work_time' => $m['workTime'],
            'subdistrict_id' => $m['subdistrict'],
            'city_id' => $m['city'],
            'province_id' => $m['state'],
            'grp_pos_id' => $m['level'],
            'hard_skill' => 'PHP',
            'soft_skill' => 'Teamwork',
            'benefits' => 'BPJS',
            'notes' => 'None',
        ], $override);
    }

    protected function insertJob(int $companyId, array $override = []): int
    {
        $title = $override['title'] ?? 'Seed Job '.Str::random(8);

        return DB::table('jobs')->insertGetId(array_merge([
            'company_id' => $companyId,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'deadline' => now()->addDays(10)->format('Y-m-d'),
            'apply_on' => 'app',
            'min_salary' => 1, 'max_salary' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ], $override));
    }
}
