<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_profiles', function (Blueprint $table): void {
            $table->id();
            // The account is a plain column: accounts live in another context and have no foreign key.
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 10)->nullable();
            $table->foreignId('religion_id')->nullable()->constrained('reference_religions')->restrictOnDelete();
            $table->foreignId('nationality_id')->nullable()->constrained('reference_nationalities')->restrictOnDelete();
            $table->unsignedBigInteger('expected_salary')->nullable();
            $table->foreignId('expected_salary_period_id')->nullable()->constrained('reference_salary_periods')->restrictOnDelete();
            $table->foreignId('experience_level_id')->nullable()->constrained('reference_experience_levels')->restrictOnDelete();
            $table->string('availability', 30)->nullable();
            $table->date('available_from')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('reference_provinces')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('reference_cities')->restrictOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained('reference_subdistricts')->restrictOnDelete();
            $table->foreignId('village_id')->nullable()->constrained('reference_villages')->restrictOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained('reference_postal_codes')->restrictOnDelete();
            $table->string('address_line', 255)->nullable();
            $table->string('headline', 100)->nullable();
            $table->string('summary', 2000)->nullable();
            $table->boolean('is_discoverable')->default(false);
            $table->boolean('resume_discoverable')->default(false);
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'jobseeker_profiles';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_gender_check\" CHECK (\"gender\" IS NULL OR \"gender\" IN ('male', 'female'))"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_marital_status_check\" CHECK (\"marital_status\" IS NULL OR \"marital_status\" IN ('single', 'married', 'divorced', 'widowed'))"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_availability_check\" CHECK (\"availability\" IS NULL OR \"availability\" IN ('open_to_work', 'employed_open_to_offers', 'not_looking'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_profiles');
    }
};
