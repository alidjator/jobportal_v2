<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_benefits', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('name');
            $table->string('slug', 150)->unique();
            $table->string('status', 10)->default('active');
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('proposed_by_company_id')->nullable()->index();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'reference_benefits';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('active', 'pending', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_benefits');
    }
};
