<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_views', function (Blueprint $table): void {
            $table->id();
            // The job lives in this context but the log is append-only and outlives nothing: a plain column keeps the insert cheap.
            $table->unsignedBigInteger('job_id');
            // `u<user_id>` for a signed-in viewer, otherwise a hash of the address and the browser salted per day (no raw address is stored).
            $table->string('viewer_key', 64);
            // The Jakarta calendar day of the view.
            $table->date('day');
            $table->timestamp('created_at')->useCurrent();

            // One counted view per viewer, job and day: a repeated view is an ignored insert.
            $table->unique(['job_id', 'viewer_key', 'day'], 'job_views_unique');
            $table->index(['job_id', 'day'], 'job_views_job_day_index');
            $table->index('day', 'job_views_day_index');
            $table->index('viewer_key', 'job_views_viewer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_views');
    }
};
