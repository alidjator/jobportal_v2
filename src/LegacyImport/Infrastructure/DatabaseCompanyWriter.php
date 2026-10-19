<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Src\LegacyImport\Application\CompanyWriter;
use Src\LegacyImport\Application\NewCompany;

final readonly class DatabaseCompanyWriter implements CompanyWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function slugTaken(string $slug): bool
    {
        return $this->connection->table('company_companies')->where('slug', $slug)->exists();
    }

    public function insert(NewCompany $company): int
    {
        $id = (int) $this->connection->table('company_companies')->insertGetId([
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => 'active',
            'website' => $company->website,
            'established_on' => $company->establishedOn,
            'bio' => $company->bio,
            'vision' => $company->vision,
            'industry_id' => $company->industryId,
            'organization_type_id' => $company->organizationTypeId,
            'team_size_id' => $company->teamSizeId,
            'is_visible' => $company->isVisible,
            'verified_at' => $company->verifiedAt,
            'created_at' => $company->createdAt,
            'updated_at' => $company->updatedAt,
        ]);

        // The legacy company has one address: it becomes the main work location ("Kantor Pusat"), written the way a new company gets it.
        $this->connection->table('company_locations')->insert([
            'company_id' => $id,
            'name' => 'Kantor Pusat',
            'type' => 'head_office',
            'is_main' => true,
            'status' => 'active',
            'address_line' => $company->addressLine,
            'province_id' => $company->location->provinceId,
            'city_id' => $company->location->cityId,
            'subdistrict_id' => $company->location->subdistrictId,
            'village_id' => $company->location->villageId,
            'postal_code_id' => $company->location->postalCodeId,
            'latitude' => $company->latitude,
            'longitude' => $company->longitude,
            'created_at' => $company->createdAt,
            'updated_at' => $company->updatedAt,
        ]);

        $this->connection->table('company_members')->insert([
            'company_id' => $id,
            'user_id' => $company->ownerUserId,
            'role' => 'company_admin',
            'is_owner' => true,
            'created_at' => $company->createdAt,
            'updated_at' => $company->createdAt,
        ]);

        return $id;
    }

    public function setImageIfEmpty(int $companyId, string $kind, string $path): bool
    {
        $column = match ($kind) {
            'logo' => 'logo_path',
            'banner' => 'banner_path',
            default => throw new InvalidArgumentException("A company has no image called {$kind}."),
        };

        return $this->connection->table('company_companies')->where('id', $companyId)->whereNull($column)->update([$column => $path]) === 1;
    }
}
