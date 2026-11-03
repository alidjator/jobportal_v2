<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` section 7 (legal question 17, behind the switch `assessment.raw_data_retention_enabled`, off): the raw data of a psychological examination that the erasure of a
 * candidate's account keeps for the institution. One row per attempt; `payload` holds the answers, the drawings with their scores, the scale scores, the norm data and the report with its revisions as JSON,
 * encrypted with the application key; `files` lists the copies kept on the private disk (path, kind, name, SHA-256). The attempt stays, so the row does not cascade. Nothing updates a row: it is inserted, read for a
 * documented legal request and purged by date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_archived_examinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->unique()->constrained('assessment_attempts')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('assessment_providers')->restrictOnDelete();
            $table->string('report_number', 40)->nullable();
            $table->timestamp('archived_at');
            $table->date('retain_until');
            $table->text('payload');
            $table->jsonb('files');

            $table->index('retain_until');
            $table->index(['provider_id', 'report_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_archived_examinations');
    }
};
