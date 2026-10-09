<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            // The job seeker lives in Identity: a plain indexed column, no foreign key.
            $table->unsignedBigInteger('job_seeker_user_id');
            // Whoever sent the first message: the job seeker or a company member.
            $table->unsignedBigInteger('started_by_user_id');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->timestamps();

            // One thread per company and job seeker.
            $table->unique(['company_id', 'job_seeker_user_id'], 'messaging_conversations_pair_unique');
            $table->index(['company_id', 'last_message_at'], 'messaging_conversations_company_time_index');
            $table->index(['job_seeker_user_id', 'last_message_at'], 'messaging_conversations_seeker_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_conversations');
    }
};
