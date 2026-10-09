<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_cv_generations', function (Blueprint $table): void {
            $table->id();
            // The account lives in Identity: a plain column, no foreign key.
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->useCurrent();

            // The hourly limit counts the rows of one user in the last hour.
            $table->index(['user_id', 'created_at'], 'jobseeker_cv_generations_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_cv_generations');
    }
};
