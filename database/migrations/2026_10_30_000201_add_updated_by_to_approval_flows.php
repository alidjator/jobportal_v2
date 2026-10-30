<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The list of the approval flows says who saved each one last, next to when (`updated_at`). The account is a plain column: accounts live in another context and have no foreign key. A flow saved before
 * this column existed has none, and the list then shows only the date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_flows', function (Blueprint $table): void {
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('approval_flows', function (Blueprint $table): void {
            $table->dropColumn('updated_by_user_id');
        });
    }
};
