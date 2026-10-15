<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-jobseeker-privacy.md` sections 1 and 2: the personal data export and the reminders to complete the profile. */
return new class extends Migration
{
    public function up(): void
    {
        // The first sign-in, set once (`last_login_at` is overwritten at every sign-in). An imported account stays empty until its first sign-in after the import.
        Schema::table('identity_users', function (Blueprint $table): void {
            $table->timestamp('first_login_at')->nullable();
        });

        // Three reminders in all, ever: `reminders_sent` is never reset.
        Schema::table('jobseeker_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
        });

        // One export per request; the archive lives on the private disk until `expires_at`.
        Schema::create('jobseeker_data_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('status', 10);
            $table->string('path', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'jobseeker_data_exports_user_created_index');
            $table->index(['status', 'expires_at'], 'jobseeker_data_exports_status_expires_index');
        });

        $connection = Schema::getConnection();
        $exports = $connection->getTablePrefix().'jobseeker_data_exports';
        $connection->statement("ALTER TABLE \"{$exports}\" ADD CONSTRAINT \"{$exports}_status_check\" CHECK (\"status\" IN ('building', 'ready', 'expired', 'failed'))");

        $profiles = $connection->getTablePrefix().'jobseeker_profiles';
        $connection->statement("ALTER TABLE \"{$profiles}\" ADD CONSTRAINT \"{$profiles}_reminders_sent_check\" CHECK (\"reminders_sent\" BETWEEN 0 AND 3)");
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_data_exports');

        Schema::table('jobseeker_profiles', function (Blueprint $table): void {
            $table->dropColumn(['reminders_sent', 'last_reminded_at']);
        });

        Schema::table('identity_users', function (Blueprint $table): void {
            $table->dropColumn('first_login_at');
        });
    }
};
