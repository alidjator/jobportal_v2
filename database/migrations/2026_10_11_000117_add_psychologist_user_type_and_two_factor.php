<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string BEFORE = "'job_seeker', 'company_member'";

    private const string AFTER = "'job_seeker', 'company_member', 'psychologist'";

    public function up(): void
    {
        $this->replaceTypeConstraint(self::AFTER);

        // The TOTP second factor of a public user who must have one (a psychologist). It lives apart from `identity_users` and mirrors the
        // administrator's: the secret is encrypted by the application, the factor counts only once `confirmed_at` is set, and `last_step`
        // is the last accepted 30-second time step, so a code cannot be used twice.
        Schema::create('identity_user_two_factor', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('identity_users')->restrictOnDelete();
            $table->text('secret');
            $table->timestamp('confirmed_at')->nullable();
            $table->bigInteger('last_step')->nullable();
            $table->timestamps();
        });

        Schema::create('identity_user_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('code_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'code_hash']);
        });

        Schema::create('identity_user_two_factor_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_user_two_factor_challenges');
        Schema::dropIfExists('identity_user_recovery_codes');
        Schema::dropIfExists('identity_user_two_factor');

        // Fails while a user of the type `psychologist` exists: those rows must be removed first.
        $this->replaceTypeConstraint(self::BEFORE);
    }

    private function replaceTypeConstraint(string $types): void
    {
        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'identity_users';

        $connection->statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT \"{$tableName}_type_check\"");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ({$types}))");
    }
};
