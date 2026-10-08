<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Synthetic tables shaped like the legacy database (schema only, no real data), created once in the test database under the table prefix `legacy_` and read
 * through the `legacy` connection. They are created and emptied through that second connection, so they never touch the transaction of the test.
 */
final class LegacySchema
{
    public const string PREFIX = 'legacy_';

    /** The masters of the legacy database: the table, its translation table and the foreign key of the translations. */
    public const array TRANSLATED_MASTERS = [
        ['job_categories', 'job_category_translations', 'job_category_id'],
        ['job_roles', 'job_role_translations', 'job_role_id'],
        ['job_types', 'job_type_translations', 'job_type_id'],
        ['job_levels', 'job_level_translations', 'job_level_id'],
        ['job_work_times', 'job_work_time_translations', 'job_work_time_id'],
        ['experiences', 'experience_translations', 'experience_id'],
        ['education', 'education_translations', 'education_id'],
        ['salary_types', 'salary_type_translations', 'salary_type_id'],
        ['industry_types', 'industry_type_translations', 'industry_type_id'],
        ['organization_types', 'organization_type_translations', 'organization_type_id'],
        ['team_sizes', 'team_size_translations', 'team_size_id'],
        ['religions', 'religion_translations', 'religion_id'],
        ['skills', 'skill_translations', 'skill_id'],
        ['benefits', 'benefit_translations', 'benefit_id'],
    ];

    /** Points the `legacy` connection at the test database with the table prefix, and makes sure the synthetic tables exist and are empty. */
    public static function prepare(): void
    {
        $default = (array) config('database.connections.'.config('database.default'));

        if (($default['database'] ?? null) !== 'jobportal_v2_test') {
            throw new RuntimeException('Refusing to continue: the synthetic legacy tables are only created in jobportal_v2_test.');
        }

        config(['database.connections.legacy' => [...$default, 'prefix' => self::PREFIX]]);
        DB::purge('legacy');

        self::ensureTables();

        foreach (self::tables() as $table) {
            DB::connection('legacy')->table($table)->truncate();
        }
    }

    /** @return list<string> */
    public static function tables(): array
    {
        $tables = ['candidate_languages', 'users', 'candidates', 'companies', 'candidate_education', 'candidate_experiences', 'candidate_skill', 'candidate_language', 'candidate_resumes', 'social_links', 'jobs', 'job_skills', 'job_benefit', 'company_questions', 'company_question_job', 'application_groups', 'applied_jobs'];

        foreach (self::TRANSLATED_MASTERS as [$master, $translations]) {
            $tables[] = $master;
            $tables[] = $translations;
        }

        return $tables;
    }

    private static function ensureTables(): void
    {
        $schema = Schema::connection('legacy');

        // A table made by an older version of this schema (the village code needs a big integer) is made again.
        if ($schema->hasTable('companies') && $schema->getColumnType('companies', 'kelurahan') !== 'bigint') {
            $schema->drop('companies');
        }

        if ($schema->hasTable('candidates') && ! $schema->hasColumn('candidates', 'bio')) {
            $schema->drop('candidates');
        }

        foreach (self::TRANSLATED_MASTERS as [$master, $translations, $foreignKey]) {
            if (! $schema->hasTable($master)) {
                $schema->create($master, function (Blueprint $table): void {
                    $table->id();
                    $table->timestamps();
                });
            }

            if (! $schema->hasTable($translations)) {
                $schema->create($translations, function (Blueprint $table) use ($foreignKey): void {
                    $table->id();
                    $table->unsignedBigInteger($foreignKey);
                    $table->string('name');
                    $table->string('locale');
                    $table->timestamps();
                });
            }
        }

        if (! $schema->hasTable('users')) {
            $schema->create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('image')->default('backend/image/default.png');
                $table->string('role')->default('candidate');
                $table->boolean('status')->default(true);
                $table->boolean('is_demo_field')->default(false);
                $table->string('google_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('candidates')) {
            $schema->create('candidates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('experience_id')->nullable();
                $table->unsignedBigInteger('religion_id')->nullable();
                $table->string('title')->nullable();
                $table->string('gender')->nullable();
                $table->string('website')->nullable();
                $table->string('photo')->nullable();
                $table->text('bio')->nullable();
                $table->string('marital_status')->nullable();
                $table->date('birth_date')->nullable();
                $table->string('address')->nullable();
                $table->string('region')->nullable();
                $table->string('district')->nullable();
                $table->integer('subdistrict')->nullable();
                $table->bigInteger('kelurahan')->nullable();
                $table->string('postcode')->nullable();
                $table->string('status')->nullable();
                $table->date('available_in')->nullable();
                $table->string('whatsapp_number')->nullable();
                $table->integer('expected_salary')->nullable();
                $table->string('phone')->nullable();
                $table->string('campaign_channel')->nullable()->default('nosession');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('candidate_education')) {
            $schema->create('candidate_education', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->unsignedBigInteger('level')->nullable();
                $table->string('degree')->nullable();
                $table->string('year');
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('grade')->nullable();
                $table->string('field')->nullable();
                $table->string('name')->nullable();
            });
        }

        if (! $schema->hasTable('candidate_experiences')) {
            $schema->create('candidate_experiences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->string('company');
                $table->string('department')->nullable();
                $table->string('designation')->nullable();
                $table->string('start');
                $table->string('end')->nullable();
                $table->text('responsibilities')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->boolean('currently_working')->default(false);
                $table->string('job_title')->nullable();
                $table->string('employment_type')->nullable();
            });
        }

        if (! $schema->hasTable('candidate_skill')) {
            $schema->create('candidate_skill', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('candidate_id')->nullable();
                $table->unsignedBigInteger('skill_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('candidate_language')) {
            $schema->create('candidate_language', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->unsignedBigInteger('candidate_language_id');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('candidate_resumes')) {
            $schema->create('candidate_resumes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->string('name');
                $table->string('file');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('social_links')) {
            $schema->create('social_links', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('social_media');
                $table->string('url');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('companies')) {
            $schema->create('companies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('industry_type_id');
                $table->unsignedBigInteger('organization_type_id');
                $table->unsignedBigInteger('team_size_id')->nullable();
                $table->string('logo')->nullable();
                $table->string('banner')->nullable();
                $table->date('establishment_date')->nullable();
                $table->string('website')->nullable();
                $table->boolean('visibility')->default(true);
                $table->text('bio')->nullable();
                $table->text('vision')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('address')->nullable();
                $table->string('region')->nullable();
                $table->string('district')->nullable();
                $table->integer('subdistrict')->nullable();
                $table->bigInteger('kelurahan')->nullable();
                $table->string('postcode')->nullable();
                $table->boolean('is_profile_verified')->default(false);
                $table->dateTime('document_verified_at')->nullable();
            });
        }

        if (! $schema->hasTable('jobs')) {
            $schema->create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->unsignedBigInteger('experience_id')->nullable();
                $table->unsignedBigInteger('education_id')->nullable();
                $table->unsignedBigInteger('job_type_id')->nullable();
                $table->unsignedBigInteger('salary_type_id')->nullable();
                $table->string('title')->nullable();
                $table->string('vacancies')->nullable();
                $table->integer('min_salary')->default(0);
                $table->integer('max_salary')->default(0);
                $table->date('deadline')->nullable();
                $table->longText('description')->nullable();
                $table->string('status')->default('pending');
                $table->string('apply_on')->default('app');
                $table->string('apply_email')->nullable();
                $table->string('apply_url')->nullable();
                $table->boolean('is_remote')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('address')->nullable();
                $table->integer('district')->nullable();
                $table->integer('subdistrict')->nullable();
                $table->string('postcode')->nullable();
                $table->integer('region')->nullable();
                $table->bigInteger('parent_job_id')->nullable();
                $table->string('salary_mode')->default('range');
                $table->string('custom_salary')->nullable();
                $table->string('company_name')->nullable();
                $table->unsignedBigInteger('work_time')->nullable();
                $table->integer('city_id')->nullable();
                $table->integer('province_id')->nullable();
                $table->unsignedBigInteger('job_level_id')->nullable();
                $table->text('hard_skill')->nullable();
                $table->text('soft_skill')->nullable();
                $table->text('benefits')->nullable();
                $table->text('notes')->nullable();
                $table->string('show_salary', 1)->nullable();
            });
        }

        foreach ([['job_skills', 'skill_id'], ['job_benefit', 'benefit_id']] as [$pivot, $column]) {
            if (! $schema->hasTable($pivot)) {
                $schema->create($pivot, function (Blueprint $table) use ($column): void {
                    $table->id();
                    $table->unsignedBigInteger('job_id')->nullable();
                    $table->unsignedBigInteger($column)->nullable();
                });
            }
        }

        if (! $schema->hasTable('company_questions')) {
            $schema->create('company_questions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->boolean('reuse')->default(true);
                $table->string('title');
                $table->boolean('required')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('company_question_job')) {
            $schema->create('company_question_job', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_id')->nullable();
                $table->unsignedBigInteger('company_question_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('application_groups')) {
            $schema->create('application_groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name');
                $table->smallInteger('order')->default(0);
                $table->boolean('is_deleteable')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('applied_jobs')) {
            $schema->create('applied_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('candidate_id')->nullable();
                $table->unsignedBigInteger('job_id')->nullable();
                $table->unsignedBigInteger('candidate_resume_id')->nullable();
                $table->longText('cover_letter')->nullable();
                $table->unsignedBigInteger('application_group_id')->nullable();
                $table->smallInteger('order')->default(0);
                $table->longText('answers')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! $schema->hasTable('candidate_languages')) {
            $schema->create('candidate_languages', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->timestamps();
            });
        }
    }
}
