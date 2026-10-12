<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array QUESTION_TYPES = ['single_choice', 'multiple_response', 'true_false', 'number', 'short_text', 'long_text', 'likert', 'forced_choice', 'drawing'];

    private const array SECTION_FORMATS = ['questions', 'speed_columns', 'drawing'];

    private const array INVITATION_STATUSES = ['invited', 'started', 'submitted', 'expired', 'cancelled'];

    public function up(): void
    {
        // A video answer question (form 2 of the tele-interview design): the candidate records the answer in the browser. `think_seconds` null means the
        // think time is not limited; `answer_seconds` and `max_retakes` are set for this type only.
        Schema::table('assessment_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('think_seconds')->nullable();
            $table->unsignedSmallInteger('answer_seconds')->nullable();
            $table->unsignedSmallInteger('max_retakes')->nullable();
        });

        // The candidate's separate consent to be recorded, given when a test with video questions is started.
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->timestamp('video_consented_at')->nullable();
        });

        $this->replace('assessment_questions', 'type_check', $this->in('type', [...self::QUESTION_TYPES, 'video_answer']));
        $this->replace('assessment_sections', 'format_check', $this->in('format', [...self::SECTION_FORMATS, 'video']));
        $this->replace('assessment_invitations', 'status_check', $this->in('status', [...self::INVITATION_STATUSES, 'declined']));
        $this->replace('assessment_questions', 'video_check', '("type" = \'video_answer\' AND "answer_seconds" BETWEEN 30 AND 300 AND "max_retakes" BETWEEN 0 AND 5 AND ("think_seconds" IS NULL OR "think_seconds" BETWEEN 30 AND 300))'
            .' OR ("type" <> \'video_answer\' AND "think_seconds" IS NULL AND "answer_seconds" IS NULL AND "max_retakes" IS NULL)');
    }

    public function down(): void
    {
        $this->replace('assessment_questions', 'video_check', null);
        $this->replace('assessment_invitations', 'status_check', $this->in('status', self::INVITATION_STATUSES));
        $this->replace('assessment_sections', 'format_check', $this->in('format', self::SECTION_FORMATS));
        $this->replace('assessment_questions', 'type_check', $this->in('type', self::QUESTION_TYPES));

        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropColumn('video_consented_at');
        });

        Schema::table('assessment_questions', function (Blueprint $table): void {
            $table->dropColumn(['think_seconds', 'answer_seconds', 'max_retakes']);
        });
    }

    /** @param  list<string>  $values */
    private function in(string $column, array $values): string
    {
        return "\"{$column}\" IN ('".implode("', '", $values)."')";
    }

    /** Drops the named check constraint and adds it again with the new expression (none: only drops it). */
    private function replace(string $table, string $suffix, ?string $expression): void
    {
        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().$table;

        $connection->statement("ALTER TABLE \"{$name}\" DROP CONSTRAINT IF EXISTS \"{$name}_{$suffix}\"");

        if ($expression !== null) {
            $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_{$suffix}\" CHECK ({$expression})");
        }
    }
};
