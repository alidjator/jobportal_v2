<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_jobs', function (Blueprint $table): void {
            $table->id();
            // References to Company, Identity and ReferenceData are plain indexed columns: no foreign keys across bounded contexts.
            $table->unsignedBigInteger('company_id')->index();
            $table->string('title', 150);
            $table->string('slug', 160);
            $table->string('status', 20)->default('draft');
            $table->string('status_reason', 1000)->nullable();
            $table->mediumText('description')->nullable();

            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->unsignedBigInteger('job_type_id')->nullable()->index();
            $table->unsignedBigInteger('job_level_id')->nullable()->index();
            $table->unsignedBigInteger('work_time_id')->nullable()->index();
            $table->unsignedBigInteger('experience_level_id')->nullable()->index();
            $table->unsignedBigInteger('education_level_id')->nullable()->index();

            $table->unsignedSmallInteger('vacancies')->nullable();
            $table->date('deadline')->nullable();

            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->unsignedBigInteger('salary_period_id')->nullable();
            $table->boolean('salary_visible')->default(true);

            $table->string('work_mode', 10)->nullable();
            $table->unsignedBigInteger('province_id')->nullable()->index();
            $table->unsignedBigInteger('city_id')->nullable()->index();
            $table->unsignedBigInteger('subdistrict_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();
            $table->unsignedBigInteger('postal_code_id')->nullable()->index();
            $table->string('address_line', 255)->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->index(['status', 'deadline']);
            $table->index(['company_id', 'status']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'job_jobs';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_status_check` CHECK (`status` IN ('draft', 'pending_review', 'active', 'closed', 'expired', 'rejected', 'taken_down'))"
        );
        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_work_mode_check` CHECK (`work_mode` IS NULL OR `work_mode` IN ('onsite', 'hybrid', 'remote'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('job_jobs');
    }
};
