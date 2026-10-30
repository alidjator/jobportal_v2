<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-jobseeker-privacy.md` section 4: the date a job seeker was last active and the switch "open to offers". */
return new class extends Migration
{
    public function up(): void
    {
        // The Jakarta date of the last authenticated request of a job seeker, written at most once a day. Companies see a bucket of it, never the date.
        Schema::table('identity_users', function (Blueprint $table): void {
            $table->date('last_active_on')->nullable();
        });

        Schema::table('jobseeker_profiles', function (Blueprint $table): void {
            $table->boolean('open_to_offers')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('jobseeker_profiles', function (Blueprint $table): void {
            $table->dropColumn('open_to_offers');
        });

        Schema::table('identity_users', function (Blueprint $table): void {
            $table->dropColumn('last_active_on');
        });
    }
};
