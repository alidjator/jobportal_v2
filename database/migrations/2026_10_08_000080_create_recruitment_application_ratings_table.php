<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();

        Schema::create('recruitment_application_ratings', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            // The member that rated; no foreign key to accounts. One rating per member and application.
            $table->unsignedBigInteger('member_id');
            $table->unsignedTinyInteger('stars');
            $table->timestamps();

            $table->foreign('application_id', $prefix.'recruitment_ratings_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();

            $table->unique(['application_id', 'member_id'], 'recruitment_ratings_application_member_unique');
        });

        $tableName = $prefix.'recruitment_application_ratings';

        Schema::getConnection()->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_stars_check\" CHECK (\"stars\" BETWEEN 1 AND 5)");
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_ratings');
    }
};
