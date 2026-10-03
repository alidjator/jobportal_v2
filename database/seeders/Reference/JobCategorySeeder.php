<?php

namespace Database\Seeders\Reference;

/**
 * Job category. The eight categories come from the legacy master data; its leftover translations of
 * deleted categories are not carried over, and neither are the legacy icon classes or the placeholder
 * image (`image_path` stays empty). The legacy "Management" category has an Indonesian slug there and
 * gets an English one here. The Indonesian names were written for this project.
 */
class JobCategorySeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_job_categories';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Manajemen dan Kepemimpinan', 'en' => 'Management']],
            ['name' => ['id' => 'Keuangan dan Akuntansi', 'en' => 'Finance & Accounting']],
            ['name' => ['id' => 'Teknologi Informasi', 'en' => 'Information Technology']],
            ['name' => ['id' => 'Operasional dan Administrasi', 'en' => 'Operational & Administration']],
            ['name' => ['id' => 'Infrastruktur dan Jaringan', 'en' => 'Infrastructure & Network']],
            ['name' => ['id' => 'Penjualan dan Pemasaran', 'en' => 'Sales & Marketing']],
            ['name' => ['id' => 'Manajemen Proyek', 'en' => 'Project Management']],
            ['name' => ['id' => 'Lainnya', 'en' => 'Other']],
        ];
    }
}
