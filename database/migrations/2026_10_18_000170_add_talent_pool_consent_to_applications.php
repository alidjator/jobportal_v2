<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-talent-pool.md` section 1: the applicant's consent to be saved in the company's talent pool (NULL: no consent; cleared on anonymisation). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->timestamp('talent_pool_consent_at')->nullable();
        });

        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'recruitment_applications';

        // The pool list asks, for up to 1,000 people of one company, which of them still carry a consent: only the consenting rows are indexed.
        $connection->statement("CREATE INDEX \"recruitment_applications_pool_consent_index\" ON \"{$table}\" (\"company_id\", \"user_id\", \"applied_at\") WHERE \"talent_pool_consent_at\" IS NOT NULL");
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP INDEX IF EXISTS "recruitment_applications_pool_consent_index"');

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('talent_pool_consent_at');
        });
    }
};
