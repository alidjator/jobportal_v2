<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-job-posting.md` sections 2 and 3 (summary, hidden vacancies, salary note), `phase-8-rejections.md` section 3 (waiting period before applying again)
 * and `company-locations.md` "Coded: #20" (the work locations of a job, the first one by `position` is the main one).
 */
return new class extends Migration
{
    /** Column => referenced table. */
    private const array REGIONS = [
        'province_id' => 'reference_provinces',
        'city_id' => 'reference_cities',
        'subdistrict_id' => 'reference_subdistricts',
        'village_id' => 'reference_villages',
        'postal_code_id' => 'reference_postal_codes',
    ];

    public function up(): void
    {
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->string('summary', 150)->nullable();
            $table->boolean('vacancies_visible')->default(true);
            $table->string('salary_note', 150)->nullable();
            $table->unsignedSmallInteger('reapply_after_days')->nullable();
        });

        Schema::create('job_job_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('job_jobs')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('company_locations')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            // The place as it was when the job was saved: searching and showing a job never read the company's tables.
            $table->string('name', 100);
            $table->string('type', 15);
            $table->string('address_line', 255)->nullable();

            foreach (self::REGIONS as $column => $referenced) {
                $table->foreignId($column)->nullable()->constrained($referenced)->restrictOnDelete();
            }

            $table->timestamp('created_at')->nullable();

            $table->unique(['job_id', 'location_id']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_job_locations');

        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn(['summary', 'vacancies_visible', 'salary_note', 'reapply_after_days']);
        });
    }
};
