<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` section 4: the time of the last reminder of the step being decided. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_instance_steps', function (Blueprint $table): void {
            $table->timestamp('reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('approval_instance_steps', function (Blueprint $table): void {
            $table->dropColumn('reminded_at');
        });
    }
};
