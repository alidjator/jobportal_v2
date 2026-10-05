<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table => column => referenced table. Reference data and companies are never deleted (they are deactivated or suspended), so RESTRICT never
     * gets in the way. Columns that point to accounts and admins (`created_by_user_id`, `*_admin_id`, assignments) keep no foreign key, by decision.
     */
    private const array REFERENCES = [
        'job_jobs' => [
            'company_id' => 'company_companies',
            'category_id' => 'reference_job_categories',
            'role_id' => 'reference_job_roles',
            'job_type_id' => 'reference_job_types',
            'job_level_id' => 'reference_job_levels',
            'work_time_id' => 'reference_work_times',
            'experience_level_id' => 'reference_experience_levels',
            'education_level_id' => 'reference_education_levels',
            'salary_period_id' => 'reference_salary_periods',
            'province_id' => 'reference_provinces',
            'city_id' => 'reference_cities',
            'subdistrict_id' => 'reference_subdistricts',
            'village_id' => 'reference_villages',
            'postal_code_id' => 'reference_postal_codes',
        ],
        'job_job_skills' => ['skill_id' => 'reference_skills'],
        'job_job_tags' => ['tag_id' => 'reference_tags'],
        'job_job_benefits' => ['benefit_id' => 'reference_benefits'],
    ];

    public function up(): void
    {
        foreach (self::REFERENCES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column => $referenced) {
                    $table->foreign($column)->references('id')->on($referenced)->restrictOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::REFERENCES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach (array_keys($columns) as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }
    }
};
