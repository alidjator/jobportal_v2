<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One recording of a candidate for a video question. A take starts `recording` while its chunks arrive (they are kept apart until the take is
        // complete), becomes `complete` when they are joined into one file, and `rejected` when a later take replaces it or the candidate gives it up (its
        // file is deleted at once; the row stays so the retakes already used are still counted). `expires_at` is when the file is purged (set when the attempt
        // is submitted).
        Schema::create('assessment_video_takes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('status', 12)->default('recording');
            $table->string('mime_type', 20)->nullable();
            $table->string('path', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id', 'number']);
            $table->index(['status', 'started_at']);
            $table->index('expires_at');
        });

        Schema::table('assessment_answers', function (Blueprint $table): void {
            $table->foreignId('video_take_id')->nullable()->constrained('assessment_video_takes')->nullOnDelete();
        });

        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().'assessment_video_takes';

        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_status_check\" CHECK (\"status\" IN ('recording', 'complete', 'rejected'))");
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_file_check\" CHECK ((\"status\" = 'complete') = (\"path\" IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::table('assessment_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('video_take_id');
        });

        Schema::dropIfExists('assessment_video_takes');
    }
};
