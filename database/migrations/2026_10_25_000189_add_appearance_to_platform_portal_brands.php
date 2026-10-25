<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/career-modes.md` section 4: the appearance settings of the Able Pro style switcher that fit a career site next to the colour preset: the theme mode
 * (light, dark or the visitor's system), the contrast, the page width and a header in the brand colour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_portal_brands', function (Blueprint $table): void {
            $table->string('theme_mode', 8)->default('light');
            $table->boolean('contrast')->default(false);
            $table->string('width', 12)->default('container');
            $table->boolean('header_color')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('platform_portal_brands', function (Blueprint $table): void {
            $table->dropColumn(['theme_mode', 'contrast', 'width', 'header_color']);
        });
    }
};
