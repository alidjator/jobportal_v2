<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The TOTP second factor of an administrator. The secret is encrypted by the application; it is set at enrolment and
        // the factor counts only once `two_factor_confirmed_at` is set. `two_factor_last_step` is the last accepted 30-second
        // time step, so a code cannot be used twice.
        Schema::table('identity_admins', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->bigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });

        Schema::create('identity_admin_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->string('code_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_id', 'code_hash']);
        });

        Schema::create('identity_admin_two_factor_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_admin_two_factor_challenges');
        Schema::dropIfExists('identity_admin_recovery_codes');

        Schema::table('identity_admins', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
