<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` section 6: the questions a company asks about a signed report and the answers of the institution. A message belongs to the report (it goes with it), says whether a
 * company member or a psychologist wrote it, and is plain text. It is not part of the report and never reaches the candidate. The assessment erasure step deletes the messages of an erased candidate's reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_report_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('assessment_reports')->cascadeOnDelete();
            $table->string('author_kind', 20);
            $table->unsignedBigInteger('author_user_id');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_report_messages');
    }
};
