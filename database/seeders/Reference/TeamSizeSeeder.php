<?php

namespace Database\Seeders\Reference;

/**
 * Company team size. The eight values come from the legacy master data, which stores no slugs or
 * numbers. The size ranges were defined for this project: a company with N people fits a size when
 * min_size <= N < max_size, and a null max_size means no upper limit ("10 Members" is read as 2 to 9
 * people, which is an interpretation). The Indonesian names were written for this project.
 */
class TeamSizeSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_team_sizes';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Hanya Saya', 'en' => 'Only Me'], 'extra' => ['min_size' => 1, 'max_size' => 2]],
            ['name' => ['id' => '10 Anggota', 'en' => '10 Members'], 'extra' => ['min_size' => 2, 'max_size' => 10]],
            ['name' => ['id' => '10-20 Anggota', 'en' => '10-20 Members'], 'extra' => ['min_size' => 10, 'max_size' => 20]],
            ['name' => ['id' => '20-50 Anggota', 'en' => '20-50 Members'], 'extra' => ['min_size' => 20, 'max_size' => 50]],
            ['name' => ['id' => '50-100 Anggota', 'en' => '50-100 Members'], 'extra' => ['min_size' => 50, 'max_size' => 100]],
            ['name' => ['id' => '100-200 Anggota', 'en' => '100-200 Members'], 'extra' => ['min_size' => 100, 'max_size' => 200]],
            ['name' => ['id' => '200-500 Anggota', 'en' => '200-500 Members'], 'extra' => ['min_size' => 200, 'max_size' => 500]],
            ['name' => ['id' => '500+ Anggota', 'en' => '500+ Members'], 'extra' => ['min_size' => 500, 'max_size' => null]],
        ];
    }
}
