<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-hiring.md` section 1: the default text of the "hired" message, next to the other scenarios. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_message_defaults', function (Blueprint $table): void {
            $table->text('hired')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_message_defaults', function (Blueprint $table): void {
            $table->dropColumn('hired');
        });
    }
};
