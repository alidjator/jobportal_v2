<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-jobseeker-privacy.md` section 3: the companies a job seeker does not want to be found by. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_blocked_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('company_companies')->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->unique(['user_id', 'company_id'], 'jobseeker_blocked_companies_user_company_unique');
            $table->index('company_id', 'jobseeker_blocked_companies_company_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_blocked_companies');
    }
};
