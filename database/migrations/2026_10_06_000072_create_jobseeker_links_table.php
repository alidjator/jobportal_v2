<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 20);
            $table->string('url', 255);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'jobseeker_links';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ('linkedin', 'github', 'portfolio', 'website', 'instagram', 'x', 'other'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_links');
    }
};
