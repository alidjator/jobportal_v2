<?php

namespace Tests\Feature\Recruitment\JobBoard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

/**
 * Helper data uji JobBoard. Semua nilai unik/acak karena test lain berjalan paralel pada DB yang sama.
 */
abstract class JobBoardTestCase extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Data uji tidak melengkapi seluruh tabel referensi (industri, organisasi, ...).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    protected function makeUser(string $role = 'candidate', array $overrides = []): int
    {
        $key = Str::lower(Str::random(10));

        return DB::table('users')->insertGetId($overrides + [
            'name' => "user $key",
            'username' => "u-$key",
            'email' => "$key@example.test",
            'password' => bcrypt('secret'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{user: int, company: int} */
    protected function makeCompany(string $name = 'Acme Corp'): array
    {
        $user = $this->makeUser('company', ['name' => $name.' '.Str::random(5)]);
        $company = DB::table('companies')->insertGetId([
            'user_id' => $user,
            'industry_type_id' => 1,
            'organization_type_id' => 1,
            'logo' => 'uploads/logo-'.Str::random(5).'.png',
            'website' => 'https://acme.test',
            'address' => 'Main street 1',
            'country' => 'Indonesia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('application_groups')->insert([
            ['company_id' => $company, 'name' => 'No Group', 'order' => 1, 'is_deleteable' => 0],
            ['company_id' => $company, 'name' => 'All Applications', 'order' => 1, 'is_deleteable' => 1],
        ]);

        return ['user' => $user, 'company' => $company];
    }

    /** @return array{user: int, candidate: int} */
    protected function makeCandidate(array $userOverrides = []): array
    {
        $user = $this->makeUser('candidate', $userOverrides);
        $candidate = DB::table('candidates')->insertGetId([
            'user_id' => $user,
            'profile_mark' => 0, 'education_mark' => 0, 'experience_mark' => 0, 'resume_mark' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['user' => $user, 'candidate' => $candidate];
    }

    protected function makeResume(int $candidateId): int
    {
        return DB::table('candidate_resumes')->insertGetId([
            'candidate_id' => $candidateId,
            'name' => 'CV '.Str::random(4),
            'file' => 'uploads/resume-'.Str::random(6).'.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{id: int, name: string} */
    protected function makeLookup(string $table, string $fk, string $translations, ?string $name = null): array
    {
        $name ??= ucfirst($table).' '.Str::random(6);
        $row = ['created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn($table, 'slug')) {
            $row['slug'] = Str::slug($name).'-'.Str::lower(Str::random(5));
        }
        $id = DB::table($table)->insertGetId($row);
        DB::table($translations)->insert([$fk => $id, 'name' => $name, 'locale' => 'en']);

        return ['id' => $id, 'name' => $name];
    }

    /**
     * Lowongan aktif dengan tipe/role/kategori terjemahan. `$extra` menimpa kolom `jobs`.
     *
     * @return array{id: int, slug: string, title: string, type: array, role: array, category: array}
     */
    protected function makeJob(int $companyId, array $extra = []): array
    {
        $type = $extra['_type'] ?? $this->makeLookup('job_types', 'job_type_id', 'job_type_translations');
        $role = $extra['_role'] ?? $this->makeLookup('job_roles', 'job_role_id', 'job_role_translations');
        $category = $extra['_category'] ?? $this->makeLookup('job_categories', 'job_category_id', 'job_category_translations');
        unset($extra['_type'], $extra['_role'], $extra['_category']);

        $key = Str::lower(Str::random(8));
        $title = $extra['title'] ?? "Job $key";

        $id = DB::table('jobs')->insertGetId($extra + [
            'company_id' => $companyId,
            'category_id' => $category['id'],
            'role_id' => $role['id'],
            'job_type_id' => $type['id'],
            'title' => $title,
            'slug' => "job-$key",
            'vacancies' => '2',
            'min_salary' => 1000,
            'max_salary' => 5000,
            'deadline' => now()->addDays(20)->toDateString(),
            'description' => '<p>Desc</p>',
            'status' => 'active',
            'apply_on' => 'app',
            'country' => 'Indonesia',
            'waiting_for_edit_approval' => 0,
            'salary_mode' => 'range',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'slug' => DB::table('jobs')->where('id', $id)->value('slug'),
            'title' => $title,
            'type' => $type,
            'role' => $role,
            'category' => $category,
        ];
    }

    protected function actingAsUser(int $userId): void
    {
        Sanctum::actingAs(UserModel::findOrFail($userId), ['*'], 'sanctum');
    }

    /** Slug unik sehingga hasil pencarian bisa difilter ke lowongan milik test ini. */
    protected function marker(): string
    {
        return 'mk'.Str::lower(Str::random(10));
    }
}
