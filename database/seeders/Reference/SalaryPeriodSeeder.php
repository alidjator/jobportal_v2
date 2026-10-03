<?php

namespace Database\Seeders\Reference;

/**
 * Salary period. The three values come from the legacy master data ("Project Base" is a
 * per-project amount rather than a time period). The Indonesian names were written for this
 * project. Further periods are added by admins through the API.
 */
class SalaryPeriodSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_salary_periods';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Bulanan', 'en' => 'Monthly']],
            ['name' => ['id' => 'Per Jam', 'en' => 'Hourly']],
            ['name' => ['id' => 'Per Proyek', 'en' => 'Project Base']],
        ];
    }
}
