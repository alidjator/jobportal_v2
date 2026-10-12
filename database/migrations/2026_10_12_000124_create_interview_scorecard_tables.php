<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview scorecards (`docs/design/phase-7-interview-kits.md`, part 2): one scorecard per panel member and interview, a score per question, the edit
 * history of a sent scorecard, and the marker of the late-scorecard reminder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_interview_scorecards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('identity_users')->restrictOnDelete();
            // `draft` or `submitted`.
            $table->string('status', 10);
            // strong_no, no, yes or strong_yes; null while the scorecard is a draft without one.
            $table->string('recommendation', 10)->nullable();
            $table->string('note', 2000)->nullable();
            // The weighted average of the answered questions; null when none was scored.
            $table->decimal('score', 3, 2)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['interview_id', 'member_id'], 'recruitment_interview_scorecards_pair_unique');
            $table->index('member_id', 'recruitment_interview_scorecards_member_index');
        });

        Schema::create('recruitment_interview_scorecard_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('recruitment_interview_scorecards')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('recruitment_interview_questions')->cascadeOnDelete();
            // Null: the question was skipped.
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('note', 1000)->nullable();

            $table->unique(['scorecard_id', 'question_id'], 'recruitment_interview_scorecard_answers_pair_unique');
            $table->index('question_id', 'recruitment_interview_scorecard_answers_question_index');
        });

        Schema::create('recruitment_interview_scorecard_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('recruitment_interview_scorecards')->cascadeOnDelete();
            // The content before the edit: recommendation, note, score and answers.
            $table->json('previous');
            // Whether another member's scorecard was already sent when the edit was made.
            $table->boolean('others_visible');
            $table->dateTime('created_at');

            $table->index('scorecard_id', 'recruitment_interview_scorecard_revisions_scorecard_index');
        });

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dateTime('scorecard_reminded_at')->nullable();
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $scorecards = "{$prefix}recruitment_interview_scorecards";
        $answers = "{$prefix}recruitment_interview_scorecard_answers";

        $connection->statement("ALTER TABLE \"{$scorecards}\" ADD CONSTRAINT \"{$scorecards}_status_check\" CHECK (\"status\" IN ('draft', 'submitted'))");
        $connection->statement("ALTER TABLE \"{$scorecards}\" ADD CONSTRAINT \"{$scorecards}_recommendation_check\" CHECK (\"recommendation\" IS NULL OR \"recommendation\" IN ('strong_no', 'no', 'yes', 'strong_yes'))");
        $connection->statement("ALTER TABLE \"{$answers}\" ADD CONSTRAINT \"{$answers}_score_check\" CHECK (\"score\" IS NULL OR \"score\" BETWEEN 1 AND 5)");
    }

    public function down(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn('scorecard_reminded_at');
        });

        Schema::dropIfExists('recruitment_interview_scorecard_revisions');
        Schema::dropIfExists('recruitment_interview_scorecard_answers');
        Schema::dropIfExists('recruitment_interview_scorecards');
    }
};
