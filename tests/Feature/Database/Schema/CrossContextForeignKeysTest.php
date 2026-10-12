<?php

namespace Tests\Feature\Database\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

/** `docs/design/cross-context-foreign-keys.md`: the keys to accounts, admins, companies, jobs and applications, and the tables that deliberately have none. */
final class CrossContextForeignKeysTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    /** @var array<string, array<string, list<string>>> referenced table => table => columns */
    private const array EXPECTED = [
        'identity_users' => [
            'company_members' => ['user_id'],
            'company_invitations' => ['invited_by_user_id'],
            'job_assignments' => ['user_id', 'assigned_by_user_id'],
            'job_jobs' => ['created_by_user_id'],
            'recruitment_screening_questions' => ['created_by_user_id'],
            'recruitment_applications' => ['user_id'],
            'recruitment_application_notes' => ['author_id'],
            'recruitment_application_ratings' => ['member_id'],
            'recruitment_interviews' => ['interviewer_id', 'created_by'],
            'recruitment_interview_kits' => ['created_by', 'updated_by'],
            'recruitment_interview_participants' => ['user_id'],
            'recruitment_interview_scorecards' => ['member_id'],
            'recruitment_offers' => ['created_by'],
            'jobseeker_profiles' => ['user_id'],
            'jobseeker_experiences' => ['user_id'],
            'jobseeker_educations' => ['user_id'],
            'jobseeker_skills' => ['user_id'],
            'jobseeker_languages' => ['user_id'],
            'jobseeker_links' => ['user_id'],
            'jobseeker_resumes' => ['user_id'],
            'jobseeker_cv_generations' => ['user_id'],
            'messaging_conversations' => ['job_seeker_user_id', 'started_by_user_id', 'closed_by_user_id'],
            'messaging_messages' => ['sender_user_id'],
            'messaging_conversation_reads' => ['user_id'],
            'jobalert_alerts' => ['user_id'],
            'jobinvitation_invitations' => ['user_id', 'invited_by_user_id'],
            'campaign_campaigns' => ['created_by_user_id'],
            'review_reviews' => ['user_id'],
            'review_replies' => ['written_by_user_id'],
            'job_saved_jobs' => ['user_id'],
            'talentpool_entries' => ['candidate_user_id'],
            'assessment_psychologists' => ['user_id'],
            'assessment_tests' => ['created_by_user_id'],
            'assessment_invitations' => ['applicant_user_id', 'invited_by_user_id'],
            'assessment_invitation_assessors' => ['user_id'],
            'assessment_answers' => ['scored_by_user_id'],
            'identity_user_two_factor' => ['user_id'],
            'identity_user_recovery_codes' => ['user_id'],
            'identity_user_two_factor_challenges' => ['user_id'],
        ],
        'identity_admins' => [
            'job_jobs' => ['reviewed_by_admin_id'],
            'company_companies' => ['created_by_admin_id'],
            'company_documents' => ['reviewed_by_admin_id'],
            'company_invitations' => ['invited_by_admin_id'],
            'content_legal_documents' => ['created_by_admin_id'],
            'platform_settings' => ['updated_by_admin_id'],
            'platform_setting_overrides' => ['updated_by_admin_id'],
            'campaign_campaigns' => ['created_by_admin_id'],
            'newsletter_issues' => ['created_by_admin_id', 'sent_by_admin_id'],
            'review_reviews' => ['moderated_by_admin_id'],
            'review_replies' => ['moderated_by_admin_id'],
            'review_reports' => ['resolved_by_admin_id'],
            'assessment_psychologist_invitations' => ['invited_by_admin_id'],
            'assessment_tests' => ['created_by_admin_id'],
            'assessment_company_batteries' => ['granted_by_admin_id'],
        ],
        'company_companies' => [
            'recruitment_interview_kits' => ['company_id'],
            'jobinvitation_invitations' => ['company_id'],
            'campaign_campaigns' => ['company_id'],
            'review_reviews' => ['company_id'],
            'talentpool_categories' => ['company_id'],
            'talentpool_entries' => ['company_id'],
            'assessment_tests' => ['company_id'],
            'assessment_invitations' => ['company_id'],
            'assessment_company_batteries' => ['company_id'],
        ],
        'job_jobs' => ['recruitment_applications' => ['job_id'], 'jobinvitation_invitations' => ['job_id'], 'recruitment_job_questions' => ['job_id'], 'recruitment_job_stage_kits' => ['job_id'], 'job_saved_jobs' => ['job_id'], 'assessment_invitations' => ['job_id']],
        'recruitment_applications' => ['jobinvitation_invitations' => ['application_id'], 'assessment_invitations' => ['application_id']],
    ];

    /** @var list<string> attachments of a job: they go away with it, so a draft with one can still be deleted */
    private const array CASCADING = ['recruitment_job_questions', 'recruitment_job_stage_kits'];

    /** @var array<string, list<string>> logs, views, audit, notifications, deliveries and summaries keep plain columns */
    private const array WITHOUT = [
        'recruitment_application_stage_history' => ['actor_id', 'from_stage_id', 'to_stage_id'],
        'jobseeker_profile_views' => ['user_id', 'company_id', 'member_id'],
        'job_views' => ['job_id'],
        'job_view_daily' => ['job_id'],
        'jobalert_deliveries' => ['job_id'],
        'matching_deliveries' => ['user_id', 'job_id'],
        'platform_audit_logs' => ['actor_id', 'subject_id'],
        'legacyimport_id_map' => ['legacy_id', 'new_id'],
        'job_market_daily' => ['company_id', 'category_id', 'province_id'],
        'review_reports' => ['reporter_id'],
    ];

    /** @return array<string, array{referenced: string, delete: string}> keyed by "table.column" */
    private function keys(string $table): array
    {
        $keys = [];

        foreach (Schema::getForeignKeys($table) as $key) {
            foreach ($key['columns'] as $column) {
                $keys["{$table}.{$column}"] = ['referenced' => $key['foreign_table'], 'delete' => $key['on_delete']];
            }
        }

        return $keys;
    }

    public function test_every_listed_column_points_to_its_table_and_restricts_the_delete(): void
    {
        foreach (self::EXPECTED as $referenced => $tables) {
            foreach ($tables as $table => $columns) {
                $keys = $this->keys($table);

                foreach ($columns as $column) {
                    $this->assertArrayHasKey("{$table}.{$column}", $keys, "{$table}.{$column} has no foreign key");
                    $this->assertSame($referenced, $keys["{$table}.{$column}"]['referenced'], "{$table}.{$column}");
                    $this->assertSame(in_array($table, self::CASCADING, true) ? 'cascade' : 'restrict', $keys["{$table}.{$column}"]['delete'], "{$table}.{$column}");
                }
            }
        }
    }

    public function test_logs_views_audit_and_summaries_have_no_foreign_key_on_these_columns(): void
    {
        foreach (self::WITHOUT as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertArrayNotHasKey("{$table}.{$column}", $this->keys($table), "{$table}.{$column}");
            }
        }
    }

    public function test_a_row_for_an_account_that_does_not_exist_is_refused(): void
    {
        $this->expectException(QueryException::class);

        DB::table('jobseeker_links')->insert(['user_id' => 999_999, 'type' => 'github', 'url' => 'https://github.com/nobody']);
    }

    public function test_a_review_by_an_admin_that_does_not_exist_is_refused(): void
    {
        $this->expectException(QueryException::class);

        DB::table('platform_settings')->insert(['key' => 'probe.limit', 'value' => json_encode(1), 'updated_by_admin_id' => 999_999, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_account_that_is_still_used_cannot_be_deleted(): void
    {
        $user = $this->userRowId('used@example.test');
        DB::table('jobseeker_links')->insert(['user_id' => $user, 'type' => 'github', 'url' => 'https://github.com/used']);

        $this->expectException(QueryException::class);

        DB::table('identity_users')->where('id', $user)->delete();
    }

    public function test_a_job_that_has_an_application_cannot_be_deleted(): void
    {
        $user = $this->userRowId('applicant@example.test');
        $company = $this->companyRowId();
        $job = (int) DB::table('job_jobs')->insertGetId(['company_id' => $company, 'title' => 'Job', 'slug' => 'job', 'created_by_user_id' => $user, 'created_at' => now(), 'updated_at' => now()]);
        $stage = (int) DB::table('recruitment_stages')->insertGetId(['company_id' => $company, 'name' => 'Applied', 'type' => 'applied', 'position' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recruitment_applications')->insert([
            'user_id' => $user, 'job_id' => $job, 'company_id' => $company, 'stage_id' => $stage, 'stage_type' => 'applied', 'active_key' => "{$user}-{$job}",
            'applied_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('job_jobs')->where('id', $job)->delete();
    }

    public function test_a_draft_job_is_deleted_together_with_its_attached_questions(): void
    {
        $user = $this->userRowId();
        $company = $this->companyRowId();
        $job = (int) DB::table('job_jobs')->insertGetId(['company_id' => $company, 'title' => 'Job', 'slug' => 'job', 'created_by_user_id' => $user, 'created_at' => now(), 'updated_at' => now()]);
        $question = (int) DB::table('recruitment_screening_questions')->insertGetId(['company_id' => $company, 'type' => 'single_choice', 'text' => 'Can you relocate?', 'created_by_user_id' => $user, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recruitment_job_questions')->insert(['job_id' => $job, 'question_id' => $question, 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('job_jobs')->where('id', $job)->delete();

        $this->assertSame(0, DB::table('recruitment_job_questions')->where('job_id', $job)->count());
        $this->assertSame(1, DB::table('recruitment_screening_questions')->where('id', $question)->count(), 'The question stays in the company bank.');
    }
}
