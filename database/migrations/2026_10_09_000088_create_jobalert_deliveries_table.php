<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobalert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_id')->constrained('jobalert_alerts')->cascadeOnDelete();
            // The job lives in Job: a plain column, no foreign key.
            $table->unsignedBigInteger('job_id');
            $table->timestamp('created_at')->useCurrent();

            // An immediate alert tells about a job once.
            $table->unique(['alert_id', 'job_id'], 'jobalert_deliveries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobalert_deliveries');
    }
};
