<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A registration link with a code, owned by a company or (company_id null) by the platform. The code is stored in lower case and never changes.
        Schema::create('campaign_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('company_companies')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 150);
            $table->string('type', 30);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamps();

            $table->unique('code', 'campaign_campaigns_code_unique');
            $table->index(['company_id', 'id'], 'campaign_campaigns_company_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'campaign_campaigns';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ('job_fair', 'education_institution', 'government_institution', 'training_institution', 'partner', 'job_marketplace', 'social_media'))"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_period_check\" CHECK (\"starts_on\" IS NULL OR \"ends_on\" IS NULL OR \"starts_on\" <= \"ends_on\")"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_creator_check\" CHECK (num_nonnulls(\"created_by_user_id\", \"created_by_admin_id\") = 1)"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_code_lower_check\" CHECK (\"code\" = lower(\"code\"))"
        );

        // The results count the accounts registered with a code; the code of an imported account keeps its case, so the match is on the lower-cased value.
        $users = $connection->getTablePrefix().'identity_users';
        $connection->statement(
            "CREATE INDEX \"identity_users_campaign_code_index\" ON \"{$users}\" (lower(\"registration_campaign_code\")) WHERE \"registration_campaign_code\" IS NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP INDEX IF EXISTS "identity_users_campaign_code_index"');
        Schema::dropIfExists('campaign_campaigns');
    }
};
