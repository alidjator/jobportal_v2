<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-activation.md` sections 3 and 11: the log of the activation emails sent to imported accounts (one `activation` and one `reminder` per account, any number of
 * `manual` ones). A delivery log: `user_id` has no foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_activation_emails', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('kind', 10);
            $table->string('variant', 12);
            $table->string('batch', 40)->index();
            $table->timestamp('sent_at');
        });

        DB::statement('CREATE UNIQUE INDEX "identity_activation_emails_automatic_unique" ON "identity_activation_emails" ("user_id", "kind") WHERE "kind" IN (\'activation\', \'reminder\')');
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_activation_emails');
    }
};
