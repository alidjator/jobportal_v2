<?php

namespace Database\Seeders\Reference;

/**
 * Company organization type. The six named values come from the legacy master data, which also holds
 * a seventh row without any name that is not carried over. The legacy table has no slugs. The
 * Indonesian names were written for this project.
 */
class OrganizationTypeSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_organization_types';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Pemerintah', 'en' => 'Government']],
            ['name' => ['id' => 'Semi Pemerintah', 'en' => 'Semi Government']],
            ['name' => ['id' => 'Publik', 'en' => 'Public']],
            ['name' => ['id' => 'Swasta', 'en' => 'Private']],
            ['name' => ['id' => 'LSM', 'en' => 'NGO']],
            ['name' => ['id' => 'Lembaga Internasional', 'en' => 'International Agencies']],
        ];
    }
}
