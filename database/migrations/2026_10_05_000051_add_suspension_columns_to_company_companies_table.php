<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When and why an admin suspended the company; both are cleared when it is restored. Only admins see the reason. */
    public function up(): void
    {
        Schema::table('company_companies', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('suspension_reason', 1000)->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('company_companies', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
