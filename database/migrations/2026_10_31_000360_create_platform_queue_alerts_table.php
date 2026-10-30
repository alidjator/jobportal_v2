<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-platform-admin.md` section 9: what the platform told the operations address about each queue. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_queue_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('queue', 100);
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->unsignedBigInteger('last_failed_job_id')->default(0);

            $table->unique(['kind', 'queue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_queue_alerts');
    }
};
