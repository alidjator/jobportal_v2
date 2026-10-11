<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One attempt per invitation. The candidate works through the sections in order: `section_position` is the section that is open and
        // `section_opened_at` when it opened, so the server knows the deadline of every section. `ends_at` is when the last section closes.
        Schema::create('assessment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->unique()->constrained('assessment_invitations')->restrictOnDelete();
            $table->timestamp('consented_at');
            $table->timestamp('started_at');
            $table->unsignedSmallInteger('section_position')->default(1);
            $table->timestamp('section_opened_at');
            $table->unsignedSmallInteger('question_cursor')->default(0);
            $table->timestamp('ends_at');
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->unsignedInteger('seed');
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['submitted_at', 'ends_at']);
        });

        // The questions drawn for this attempt, in the order they are asked.
        Schema::create('assessment_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('assessment_sections')->restrictOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');

            $table->unique(['attempt_id', 'question_id']);
            $table->index(['attempt_id', 'section_id', 'position']);
        });

        // The choices are kept as the positions of the options (they do not change: a test with invitations is locked).
        Schema::create('assessment_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->jsonb('option_positions')->nullable();
            $table->decimal('number_value', 20, 6)->nullable();
            $table->text('text_value')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->foreignId('scored_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('answered_at');

            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('assessment_speed_column_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('assessment_sections')->restrictOnDelete();
            $table->unsignedSmallInteger('column_no');
            $table->unsignedSmallInteger('attempted');
            $table->unsignedSmallInteger('correct');

            $table->unique(['attempt_id', 'section_id', 'column_no']);
        });

        Schema::create('assessment_drawings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->string('path', 255);
            $table->string('source', 10);
            $table->string('mime_type', 20);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('assessment_integrity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('occurred_at');

            $table->index(['attempt_id', 'type']);
        });

        // Made when the attempt is submitted and recomputed when an assessor scores a long-text answer. `scale_scores` holds the raw score of each
        // psychological scale; `pending_scoring` means a long-text answer still waits for its assessor.
        Schema::create('assessment_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->unique()->constrained('assessment_attempts')->cascadeOnDelete();
            $table->string('status', 20);
            $table->jsonb('section_scores');
            $table->jsonb('scale_scores')->nullable();
            $table->decimal('earned_points', 10, 2)->nullable();
            $table->decimal('max_points', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('band', 20)->nullable();
            $table->string('integrity_tier', 10);
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $checks = [
            'assessment_drawings' => ['source_check' => "\"source\" IN ('canvas', 'upload')"],
            'assessment_integrity_events' => ['type_check' => "\"type\" IN ('tab_switch', 'fullscreen_exit', 'paste', 'ip_change')"],
            'assessment_results' => [
                'status_check' => "\"status\" IN ('scored', 'pending_scoring')",
                'tier_check' => "\"integrity_tier\" IN ('none', 'some', 'major')",
            ],
        ];

        foreach ($checks as $table => $constraints) {
            $name = $prefix.$table;

            foreach ($constraints as $suffix => $expression) {
                $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_{$suffix}\" CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessment_integrity_events');
        Schema::dropIfExists('assessment_drawings');
        Schema::dropIfExists('assessment_speed_column_results');
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_attempt_questions');
        Schema::dropIfExists('assessment_attempts');
    }
};
