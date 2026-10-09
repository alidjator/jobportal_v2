<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobinvitation_invitations', function (Blueprint $table): void {
            $table->id();
            // The company, the job, the candidate and the member live in other contexts: plain columns, no foreign keys.
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->string('note', 1000)->nullable();
            $table->string('status', 10)->default('sent');
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->timestamps();

            // One invitation per candidate and job.
            $table->unique(['job_id', 'user_id'], 'jobinvitation_invitations_job_user_unique');
            $table->index(['user_id', 'created_at'], 'jobinvitation_invitations_user_index');
            $table->index(['company_id', 'created_at'], 'jobinvitation_invitations_company_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'jobinvitation_invitations';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_status_check` CHECK (`status` IN ('sent', 'viewed', 'applied', 'declined'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('jobinvitation_invitations');
    }
};
