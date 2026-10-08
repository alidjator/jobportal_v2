<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_offers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('position', 150);
            // Monthly salary in Rupiah.
            $table->unsignedBigInteger('salary');
            $table->date('start_date');
            $table->string('note', 2000)->nullable();
            // The job seeker can answer until this moment; the hourly command then marks a pending offer as expired.
            $table->dateTime('expires_at');
            // The member who sent it; no foreign key to accounts.
            $table->unsignedBigInteger('created_by');
            // pending, accepted, declined, withdrawn or expired.
            $table->string('status', 10);
            $table->string('answer_note', 1000)->nullable();
            $table->dateTime('answered_at')->nullable();
            // The application id while the offer is pending and null afterwards, so an application has at most one pending offer.
            $table->unsignedBigInteger('active_key')->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->timestamps();

            $table->foreign('application_id', Schema::getConnection()->getTablePrefix().'recruitment_offers_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();

            $table->unique('active_key', 'recruitment_offers_active_unique');
            $table->index(['application_id', 'created_at'], 'recruitment_offers_application_time_index');
            $table->index(['status', 'expires_at'], 'recruitment_offers_status_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_offers');
    }
};
