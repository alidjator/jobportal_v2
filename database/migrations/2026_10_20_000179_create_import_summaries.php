<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-activation.md` sections 5 and 11: what the karir.pkp import did and did not carry, one row per imported job seeker and one per imported company, written by
 * the importers. `items` is a list of `{code, count, detail}` (counts only, no personal content).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_import_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('identity_users')->restrictOnDelete();
            $table->jsonb('items');
            $table->timestamp('created_at');
            $table->timestamp('dismissed_at')->nullable();
        });

        Schema::create('company_import_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('company_companies')->restrictOnDelete();
            $table->jsonb('items');
            $table->timestamp('created_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_import_summaries');
        Schema::dropIfExists('jobseeker_import_summaries');
    }
};
