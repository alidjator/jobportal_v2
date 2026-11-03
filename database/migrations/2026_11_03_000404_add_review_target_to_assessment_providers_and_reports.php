<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` section 5: an institution sets how many working days its psychologists take to review a report (`review_target_days`, 1 to 10, default 2), and a
 * report that waits for a review keeps the day it is due (`review_due_on`: the submission of the attempt plus the target in working days, Jakarta). It is worked out once when the draft is made, so
 * changing the target later does not move the days of the reports that already wait. A report issued automatically has none, and neither has one that existed before this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('review_target_days')->default(2);
        });

        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->date('review_due_on')->nullable();
            $table->index(['provider_id', 'review_due_on']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->dropIndex(['provider_id', 'review_due_on']);
            $table->dropColumn('review_due_on');
        });

        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->dropColumn('review_target_days');
        });
    }
};
