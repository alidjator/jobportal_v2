<?php

namespace Database\Seeders\Reference;

/**
 * Employment relationship. Contract, Permanent and Internship come from the legacy master data;
 * Freelance was added by the Phase 0 design. The Indonesian names were written for this project.
 */
class JobTypeSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_job_types';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Kontrak', 'en' => 'Contract']],
            ['name' => ['id' => 'Tetap', 'en' => 'Permanent']],
            ['name' => ['id' => 'Magang', 'en' => 'Internship']],
            ['name' => ['id' => 'Freelance', 'en' => 'Freelance']],
        ];
    }
}
