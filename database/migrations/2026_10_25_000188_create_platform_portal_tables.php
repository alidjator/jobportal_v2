<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/career-modes.md` section 12.2: the live values of the career portal (its identity, one row, and the brand of a group or a company) and the drafts that wait
 * to be published. No row of identity means the marketplace; no row of brand means the default brand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_portal_identity', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 16);
            $table->string('scope', 16)->nullable();
            $table->jsonb('company_ids');
            // What the last warning email named (a hash of the skipped companies); null when nothing was skipped at the last check.
            $table->string('alert_signature', 64)->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('platform_portal_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 16);
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 120)->nullable();
            $table->string('tagline_id', 160)->nullable();
            $table->string('tagline_en', 160)->nullable();
            $table->string('welcome_id', 500)->nullable();
            $table->string('welcome_en', 500)->nullable();
            $table->unsignedTinyInteger('preset')->default(1);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['owner_type', 'owner_id'], 'platform_portal_brands_owner_unique');
        });

        Schema::create('platform_portal_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 16);
            // 0 for the platform, which has no id (a null would let a second row in).
            $table->unsignedBigInteger('owner_id')->default(0);
            $table->jsonb('payload');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['owner_type', 'owner_id'], 'platform_portal_drafts_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_portal_drafts');
        Schema::dropIfExists('platform_portal_brands');
        Schema::dropIfExists('platform_portal_identity');
    }
};
