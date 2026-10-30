<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_check_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->string('instructions', 2000)->nullable();
            $table->boolean('requires_consent')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_id', 'recruitment_check_types_company_index');
        });

        Schema::create('recruitment_application_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('check_type_id');
            $table->string('status', 12)->default('requested');
            // Whether the candidate's consent was required when the check was requested (the type may change later).
            $table->boolean('consent_required')->default(true);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('place', 200)->nullable();
            $table->string('vendor', 150)->nullable();
            $table->string('note_to_candidate', 1000)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('consent_refused_at')->nullable();
            $table->string('refusal_reason', 500)->nullable();
            $table->string('outcome', 16)->nullable();
            $table->string('result_note', 1000)->nullable();
            $table->string('result_file_path')->nullable();
            $table->string('result_file_name', 100)->nullable();
            $table->unsignedBigInteger('result_recorded_by')->nullable();
            $table->timestamp('result_recorded_at')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->timestamps();

            $table->index('application_id', 'recruitment_application_checks_application_index');
            $table->index(['status', 'result_recorded_at'], 'recruitment_application_checks_status_index');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $types = $prefix.'recruitment_check_types';
        $checks = $prefix.'recruitment_application_checks';

        $connection->statement('ALTER TABLE "'.$types.'" ADD CONSTRAINT "'.$types.'_kind_check" CHECK ("kind" IN (\'medical\', \'background\', \'reference\', \'police_record\', \'education\', \'other\'))');
        $connection->statement('CREATE UNIQUE INDEX "recruitment_check_types_name_unique" ON "'.$types.'" ("company_id", lower("name"))');
        $connection->statement('ALTER TABLE "'.$checks.'" ADD CONSTRAINT "'.$checks.'_status_check" CHECK ("status" IN (\'requested\', \'consented\', \'scheduled\', \'completed\', \'waived\', \'cancelled\'))');
        $connection->statement('ALTER TABLE "'.$checks.'" ADD CONSTRAINT "'.$checks.'_outcome_check" CHECK ("outcome" IS NULL OR "outcome" IN (\'pass\', \'pass_with_notes\', \'fail\'))');
        // One open check per type and application.
        $connection->statement('CREATE UNIQUE INDEX "recruitment_application_checks_open_unique" ON "'.$checks.'" ("application_id", "check_type_id") WHERE "status" IN (\'requested\', \'consented\', \'scheduled\')');
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_checks');
        Schema::dropIfExists('recruitment_check_types');
    }
};
