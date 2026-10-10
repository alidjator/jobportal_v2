<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL indexes the referenced side of a foreign key but not the referencing column, so every join, filter and parent
     * delete check on these columns scanned the whole table. One index each, in the default Laravel name `<table>_<column>_index`.
     *
     * @var array<string, list<string>>
     */
    private const FOREIGN_KEY_INDEXES = [
        'campaign_campaigns' => ['created_by_admin_id', 'created_by_user_id'],
        'company_documents' => ['document_type_id'],
        'company_invitations' => ['company_id'],
        'content_faqs' => ['category_id'],
        'identity_admin_two_factor_challenges' => ['admin_id'],
        'identity_role_permissions' => ['role_id'],
        'job_jobs' => ['salary_period_id'],
        'jobseeker_educations' => ['education_level_id'],
        'jobseeker_experiences' => ['job_type_id'],
        'jobseeker_languages' => ['language_id'],
        'jobseeker_profiles' => [
            'city_id', 'expected_salary_period_id', 'experience_level_id', 'nationality_id',
            'postal_code_id', 'province_id', 'religion_id', 'subdistrict_id', 'village_id',
        ],
        'jobseeker_skills' => ['skill_id'],
        'newsletter_deliveries' => ['subscriber_id'],
        'newsletter_issues' => ['created_by_admin_id', 'sent_by_admin_id'],
        'notification_device_tokens' => ['user_id'],
        'recruitment_application_answer_options' => ['option_id'],
        'recruitment_application_answers' => ['question_id'],
        'recruitment_applications' => ['company_id', 'stage_id'],
        'recruitment_job_question_ideal_options' => ['option_id'],
        'recruitment_job_questions' => ['question_id'],
        'reference_cities' => ['province_id'],
        'reference_subdistricts' => ['city_id'],
        'reference_villages' => ['subdistrict_id'],
        'review_replies' => ['moderated_by_admin_id', 'written_by_user_id'],
        'review_reports' => ['resolved_by_admin_id', 'review_id'],
        'review_reviews' => ['moderated_by_admin_id'],
        'talentpool_entries' => ['category_id'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEY_INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }

        // Covered by the composite indexes `job_jobs_company_id_status_index` and `recruitment_job_questions_job_question_unique`;
        // each only cost a write per row change.
        Schema::table('job_jobs', fn (Blueprint $table) => $table->dropIndex('job_jobs_company_id_index'));
        Schema::table('recruitment_job_questions', fn (Blueprint $table) => $table->dropIndex('recruitment_job_questions_job_id_index'));

        // The candidate search sorts the discoverable, complete profiles by recency; this serves that order without a sort.
        DB::statement(
            'CREATE INDEX jobseeker_profiles_discoverable_recent_index ON jobseeker_profiles (updated_at DESC, id DESC) '
            .'WHERE is_discoverable AND slug IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS jobseeker_profiles_discoverable_recent_index');

        Schema::table('recruitment_job_questions', fn (Blueprint $table) => $table->index('job_id'));
        Schema::table('job_jobs', fn (Blueprint $table) => $table->index('company_id'));

        foreach (self::FOREIGN_KEY_INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
