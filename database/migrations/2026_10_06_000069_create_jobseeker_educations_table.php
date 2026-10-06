<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_educations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('education_level_id')->constrained('reference_education_levels')->restrictOnDelete();
            $table->string('institution', 150);
            $table->string('field_of_study', 100)->nullable();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->boolean('is_current')->default(false);
            $table->decimal('gpa', 3, 2)->unsigned()->nullable();
            $table->string('description', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_educations');
    }
};
