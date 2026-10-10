<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The email list of the newsletter: addresses only, no account. An `unsubscribed` row stays on purpose (it is the suppression list).
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254);
            $table->string('status', 15);
            $table->string('locale', 2);
            $table->string('confirm_token_hash', 64)->nullable();
            $table->timestamp('confirm_expires_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique('email', 'newsletter_subscribers_email_unique');
            $table->index('confirm_token_hash', 'newsletter_subscribers_token_index');
            $table->index(['status', 'id'], 'newsletter_subscribers_status_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'newsletter_subscribers';

        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('pending', 'confirmed', 'unsubscribed'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_locale_check\" CHECK (\"locale\" IN ('id', 'en'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_email_lower_check\" CHECK (\"email\" = lower(\"email\"))");
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
