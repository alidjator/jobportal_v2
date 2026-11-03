<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-tools.md` section 5: what the notices to a psychologist remember. `reviews_notified_count` is how many reviews waited when the psychologist was last told (the
 * hourly notice goes out only when the count rises); `licence_notified` holds, per licence (`str`, `silp`), the end date it was about and the thresholds (60, 30, 7 and 0 days) already told, so
 * a renewed licence starts again without any reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_psychologists', function (Blueprint $table): void {
            $table->unsignedInteger('reviews_notified_count')->default(0);
            $table->jsonb('licence_notified')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_psychologists', function (Blueprint $table): void {
            $table->dropColumn(['reviews_notified_count', 'licence_notified']);
        });
    }
};
