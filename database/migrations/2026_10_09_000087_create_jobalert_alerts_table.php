<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobalert_alerts', function (Blueprint $table): void {
            $table->id();
            // The job seeker lives in Identity: a plain indexed column, no foreign key.
            $table->unsignedBigInteger('user_id');
            $table->string('name', 100);
            // immediate, daily or weekly.
            $table->string('frequency', 16);
            // The search filters, by the names of the public job search.
            $table->jsonb('criteria');
            $table->boolean('email')->default(true);
            $table->boolean('in_app')->default(true);
            $table->boolean('paused')->default(false);
            // The cursor: only jobs that first went live after it are sent.
            $table->dateTime('last_run_at');
            // The Jakarta day of the immediate emails counted in `emails_sent`.
            $table->date('emails_day')->nullable();
            $table->unsignedSmallInteger('emails_sent')->default(0);
            $table->timestamps();

            $table->index('user_id', 'jobalert_alerts_user_index');
            $table->index(['frequency', 'paused', 'id'], 'jobalert_alerts_run_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobalert_alerts');
    }
};
