<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-platform-admin.md` section 8: what the platform remembers about each scheduled task. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_scheduled_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('task', 150)->unique();
            $table->string('expression', 100);
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_failure', 1000)->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_scheduled_task_runs');
    }
};
