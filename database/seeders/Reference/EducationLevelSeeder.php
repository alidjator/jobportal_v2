<?php

namespace Database\Seeders\Reference;

/**
 * Education level. The five values and their order come from the legacy master data, whose
 * "English" names are the Indonesian abbreviations; they are kept so the slugs stay the same.
 * The ranks were defined for this project and are used to compare minimum education: D4 and S1
 * share a rank because both are bachelor-level. The Indonesian names were written for this project.
 */
class EducationLevelSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_education_levels';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'S2 (Magister)', 'en' => 'S2'], 'extra' => ['rank' => 4]],
            ['name' => ['id' => 'S1 (Sarjana)', 'en' => 'S1'], 'extra' => ['rank' => 3]],
            ['name' => ['id' => 'D4 (Diploma 4)', 'en' => 'D4'], 'extra' => ['rank' => 3]],
            ['name' => ['id' => 'D3 (Diploma 3)', 'en' => 'D3'], 'extra' => ['rank' => 2]],
            ['name' => ['id' => 'SMA (Sekolah Menengah Atas)', 'en' => 'SMA'], 'extra' => ['rank' => 1]],
        ];
    }
}
