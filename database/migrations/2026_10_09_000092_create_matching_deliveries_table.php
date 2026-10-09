<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_deliveries', function (Blueprint $table): void {
            $table->id();
            // The job seeker and the job live in other contexts: plain columns, no foreign keys.
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('job_id');
            $table->timestamp('created_at')->useCurrent();

            // A recommendation email names a job once; the second index finds the last email of a user.
            $table->unique(['user_id', 'job_id'], 'matching_deliveries_unique');
            $table->index(['user_id', 'created_at'], 'matching_deliveries_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_deliveries');
    }
};
