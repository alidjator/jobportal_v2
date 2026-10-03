<?php

namespace Database\Seeders\Reference;

/**
 * Seniority level. The three values come from the legacy master data; the Indonesian
 * names were written for this project. Further levels are added by admins through the API.
 */
class JobLevelSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_job_levels';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Junior', 'en' => 'Junior']],
            ['name' => ['id' => 'Menengah', 'en' => 'Middle']],
            ['name' => ['id' => 'Senior', 'en' => 'Senior']],
        ];
    }
}
