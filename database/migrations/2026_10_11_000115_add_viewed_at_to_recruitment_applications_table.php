<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When a recruiter first opened the application detail; NULL for an unseen application and for applications imported from karir.pkp (unknown).
        // Never filtered or sorted on, so no index.
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->timestamp('viewed_at')->nullable()->after('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_applications', fn (Blueprint $table) => $table->dropColumn('viewed_at'));
    }
};
