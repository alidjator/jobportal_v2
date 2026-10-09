<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->string('status', 20)->default('active');
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('website')->nullable();
            $table->date('established_on')->nullable();
            $table->text('bio')->nullable();
            $table->text('vision')->nullable();
            $table->unsignedBigInteger('industry_id')->index();
            $table->unsignedBigInteger('organization_type_id')->index();
            $table->unsignedBigInteger('team_size_id')->nullable()->index();
            $table->string('address_line')->nullable();
            $table->unsignedBigInteger('province_id')->nullable()->index();
            $table->unsignedBigInteger('city_id')->nullable()->index();
            $table->unsignedBigInteger('subdistrict_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();
            $table->unsignedBigInteger('postal_code_id')->nullable()->index();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'company_companies';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('active', 'suspended'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_companies');
    }
};
