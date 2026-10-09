<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_profile_views', function (Blueprint $table): void {
            $table->id();
            // The candidate, the company and the member live in other contexts: plain columns, no foreign keys.
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('member_id');
            $table->string('kind', 10);
            // The Jakarta calendar day of the view.
            $table->date('day');
            $table->timestamp('created_at')->useCurrent();

            // One entry per member, candidate, kind and day.
            $table->unique(['member_id', 'user_id', 'kind', 'day'], 'jobseeker_profile_views_unique');
            $table->index(['user_id', 'day'], 'jobseeker_profile_views_user_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_profile_views');
    }
};
