<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\JobSeeker\Domain\ProfileSlug;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable on purpose: a profile row can be created without one (the legacy import) and gets it the first time the owner saves the profile.
        Schema::table('jobseeker_profiles', function (Blueprint $table): void {
            $table->string('slug', 40)->nullable()->unique('jobseeker_profiles_slug_unique')->after('user_id');
        });

        $used = [];

        foreach (DB::table('jobseeker_profiles')->orderBy('id')->pluck('id') as $id) {
            do {
                $slug = ProfileSlug::generate();
            } while (isset($used[$slug]));

            $used[$slug] = true;
            DB::table('jobseeker_profiles')->where('id', $id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('jobseeker_profiles', function (Blueprint $table): void {
            $table->dropUnique('jobseeker_profiles_slug_unique');
            $table->dropColumn('slug');
        });
    }
};
