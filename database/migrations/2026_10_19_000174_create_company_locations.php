<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/company-locations.md` sections 1 and 2: the work locations of a tenant. Every company gets one main location built from the address it has today, and the six
 * address columns leave `company_companies` (one source of truth). `down` puts the columns back and copies the main location into them.
 */
return new class extends Migration
{
    /** Column => referenced table. Reference data is never deleted (only deactivated), so RESTRICT never gets in the way. */
    private const array REGIONS = [
        'province_id' => 'reference_provinces',
        'city_id' => 'reference_cities',
        'subdistrict_id' => 'reference_subdistricts',
        'village_id' => 'reference_villages',
        'postal_code_id' => 'reference_postal_codes',
    ];

    public function up(): void
    {
        Schema::create('company_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('type', 15);
            $table->boolean('is_main')->default(false);
            $table->string('status', 10)->default('active');
            $table->string('address_line', 255)->nullable();

            foreach (self::REGIONS as $column => $referenced) {
                $table->foreignId($column)->nullable()->constrained($referenced)->restrictOnDelete();
            }

            $table->string('phone', 32)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'company_locations_company_status_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'company_locations';

        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ('head_office', 'branch', 'site'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_coordinates_check\" CHECK ((\"latitude\" IS NULL) = (\"longitude\" IS NULL))");
        // The main location is always active, and a tenant has at most one.
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_main_active_check\" CHECK (NOT \"is_main\" OR \"status\" = 'active')");
        $connection->statement("CREATE UNIQUE INDEX \"company_locations_company_main_unique\" ON \"{$tableName}\" (\"company_id\") WHERE \"is_main\"");
        // A name is unique within a tenant ignoring case; other tenants may use the same name.
        $connection->statement("CREATE UNIQUE INDEX \"company_locations_company_name_unique\" ON \"{$tableName}\" (\"company_id\", lower(\"name\"))");

        $companies = $connection->getTablePrefix().'company_companies';

        $connection->statement(
            "INSERT INTO \"{$tableName}\" (\"company_id\", \"name\", \"type\", \"is_main\", \"status\", \"address_line\", \"province_id\", \"city_id\", \"subdistrict_id\", \"village_id\", \"postal_code_id\", \"created_at\", \"updated_at\") "
            ."SELECT \"id\", 'Kantor Pusat', 'head_office', TRUE, 'active', \"address_line\", \"province_id\", \"city_id\", \"subdistrict_id\", \"village_id\", \"postal_code_id\", NOW(), NOW() FROM \"{$companies}\""
        );

        Schema::table('company_companies', function (Blueprint $table): void {
            $table->dropColumn(['address_line', ...array_keys(self::REGIONS)]);
        });
    }

    public function down(): void
    {
        Schema::table('company_companies', function (Blueprint $table): void {
            $table->string('address_line')->nullable();

            foreach (self::REGIONS as $column => $referenced) {
                $table->unsignedBigInteger($column)->nullable()->index();
                $table->foreign($column)->references('id')->on($referenced)->restrictOnDelete();
            }
        });

        $connection = Schema::getConnection();
        $companies = $connection->getTablePrefix().'company_companies';
        $locations = $connection->getTablePrefix().'company_locations';

        $connection->statement(
            "UPDATE \"{$companies}\" AS c SET \"address_line\" = l.\"address_line\", \"province_id\" = l.\"province_id\", \"city_id\" = l.\"city_id\", \"subdistrict_id\" = l.\"subdistrict_id\", "
            ."\"village_id\" = l.\"village_id\", \"postal_code_id\" = l.\"postal_code_id\" FROM \"{$locations}\" AS l WHERE l.\"company_id\" = c.\"id\" AND l.\"is_main\""
        );

        Schema::dropIfExists('company_locations');
    }
};
