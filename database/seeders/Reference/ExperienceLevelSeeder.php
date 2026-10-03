<?php

namespace Database\Seeders\Reference;

/**
 * Work experience level. The six values come from the legacy master data, which stores no numbers.
 * The year ranges were defined for this project: a candidate with N full years of experience fits a
 * level when min_years <= N < max_years, and a null max_years means no upper limit. The Indonesian
 * names were written for this project.
 */
class ExperienceLevelSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_experience_levels';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Lulusan Baru', 'en' => 'Fresh Graduated'], 'extra' => ['min_years' => 0, 'max_years' => 1]],
            ['name' => ['id' => 'Kurang dari 1 Tahun', 'en' => 'Less than a Year'], 'extra' => ['min_years' => 0, 'max_years' => 1]],
            ['name' => ['id' => '1-3 Tahun', 'en' => '1-3 years'], 'extra' => ['min_years' => 1, 'max_years' => 3]],
            ['name' => ['id' => '3-5 Tahun', 'en' => '3-5 years'], 'extra' => ['min_years' => 3, 'max_years' => 5]],
            ['name' => ['id' => '5-10 Tahun', 'en' => '5-10 years'], 'extra' => ['min_years' => 5, 'max_years' => 10]],
            ['name' => ['id' => 'Lebih dari 10 Tahun', 'en' => '>10 years'], 'extra' => ['min_years' => 10, 'max_years' => null]],
        ];
    }
}
