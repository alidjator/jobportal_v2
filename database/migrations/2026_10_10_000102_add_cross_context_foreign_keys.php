<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selective foreign keys between bounded contexts (`docs/design/cross-context-foreign-keys.md`): only towards stable targets, never on logs, views,
 * audit, notifications, delivery records or the `*_daily` summaries. Accounts are anonymised and never deleted, companies are suspended, and only a
 * draft job can be deleted, so RESTRICT never gets in the way of a normal flow.
 */
return new class extends Migration
{
    /** Referenced table => table => columns. */
    private const array RESTRICT = [
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
        ],
        'identity_admins' => [
            'job_jobs' => ['reviewed_by_admin_id'],
            'company_companies' => ['created_by_admin_id'],
            'company_documents' => ['reviewed_by_admin_id'],
            'company_invitations' => ['invited_by_admin_id'],
            'content_legal_documents' => ['created_by_admin_id'],
            'platform_settings' => ['updated_by_admin_id'],
            'platform_setting_overrides' => ['updated_by_admin_id'],
        ],
        'company_companies' => [
            'jobinvitation_invitations' => ['company_id'],
        ],
        'job_jobs' => [
            'recruitment_applications' => ['job_id'],
            'jobinvitation_invitations' => ['job_id'],
        ],
        'recruitment_applications' => [
            'jobinvitation_invitations' => ['application_id'],
        ],
    ];

    /** Attachments of a job go away with it, so a draft with screening questions can still be deleted. */
    private const array CASCADE = [
        'job_jobs' => [
            'recruitment_job_questions' => ['job_id'],
        ],
    ];

    public function up(): void
    {
        $this->each(function (string $table, string $column, string $referenced, bool $cascade): void {
            // A delete or update of the parent must not scan the table, so the column needs an index it leads.
            $indexed = collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === $column);

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $referenced, $cascade, $indexed): void {
                if (! $indexed) {
                    $blueprint->index($column, $this->name($table, $column, 'index'));
                }

                $foreign = $blueprint->foreign($column, $this->name($table, $column, 'foreign'))->references('id')->on($referenced);
                $cascade ? $foreign->cascadeOnDelete() : $foreign->restrictOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->each(function (string $table, string $column): void {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $column): void {
                $blueprint->dropForeign($this->name($table, $column, 'foreign'));
            });
        });
    }

    /** @param callable(string, string, string, bool): void $apply */
    private function each(callable $apply): void
    {
        foreach ([false => self::RESTRICT, true => self::CASCADE] as $cascade => $map) {
            foreach ($map as $referenced => $tables) {
                foreach ($tables as $table => $columns) {
                    foreach ($columns as $column) {
                        $apply($table, $column, $referenced, (bool) $cascade);
                    }
                }
            }
        }
    }

    /** PostgreSQL limits identifiers to 63 characters; a long name is cut and made unique by a short checksum. */
    private function name(string $table, string $column, string $suffix): string
    {
        $name = $table.'_'.$column.'_'.$suffix;

        return strlen($name) <= 63 ? $name : substr($name, 0, 54).'_'.substr(md5($name), 0, 8);
    }
};
