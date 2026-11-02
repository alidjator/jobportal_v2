<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-tools.md` section 6: a provider may attach an example report (a PDF about a fictitious candidate) to a battery, so a company can see what it
 * would receive before it asks for the battery. One file per battery; a new upload replaces it. It is marketing, not content, so invitations never lock it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_example_reports', function (Blueprint $table): void {
            $table->foreignId('test_id')->primary()->constrained('assessment_tests')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->unsignedInteger('size_bytes');
            $table->timestamp('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_example_reports');
    }
};
