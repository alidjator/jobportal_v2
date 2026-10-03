<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_template_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key', 128);
            $table->char('locale', 2);
            $table->string('subject');
            $table->mediumText('body');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            // The generated names would reach or exceed the 64-character identifier limit of MariaDB.
            $table->unique(['template_key', 'locale'], 'email_template_overrides_key_locale_unique');
            $table->index('updated_by_admin_id', 'email_template_overrides_updated_by_admin_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_template_overrides');
    }
};
