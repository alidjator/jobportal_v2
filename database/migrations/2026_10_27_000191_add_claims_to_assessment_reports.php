<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-tools.md` section 3: a psychologist claims a report before editing or signing it. The claim lapses after 24 hours without a write
 * by the holder (`claim_touched_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->foreignId('claimed_by_psychologist_id')->nullable()->constrained('assessment_psychologists')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_touched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('claimed_by_psychologist_id');
            $table->dropColumn(['claimed_at', 'claim_touched_at']);
        });
    }
};
