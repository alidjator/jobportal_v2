<?php

namespace Database\Seeders\Reference;

/**
 * Working pattern. Full Time and Part Time come from the legacy master data; Shift was added by
 * the Phase 0 design. The Indonesian names were written for this project.
 */
class WorkTimeSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_work_times';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Penuh Waktu', 'en' => 'Full Time']],
            ['name' => ['id' => 'Paruh Waktu', 'en' => 'Part Time']],
            ['name' => ['id' => 'Shift', 'en' => 'Shift']],
        ];
    }
}
