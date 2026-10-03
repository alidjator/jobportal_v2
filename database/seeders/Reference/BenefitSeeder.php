<?php

namespace Database\Seeders\Reference;

/**
 * Job benefit. The legacy `benefits` table is empty, so this is a short list of benefits that are
 * common in Indonesia, written for this project. All of them are approved (`active`) global
 * benefits; companies propose further ones and admins approve them through the API.
 */
class BenefitSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_benefits';
    }

    protected function rows(): array
    {
        return array_map(
            fn (array $names): array => ['name' => $names, 'extra' => ['status' => 'active']],
            [
                ['id' => 'BPJS Kesehatan', 'en' => 'BPJS Health Insurance'],
                ['id' => 'BPJS Ketenagakerjaan', 'en' => 'BPJS Employment'],
                ['id' => 'Tunjangan Hari Raya (THR)', 'en' => 'Religious Holiday Allowance (THR)'],
                ['id' => 'Bonus Tahunan', 'en' => 'Annual Bonus'],
                ['id' => 'Asuransi Kesehatan Swasta', 'en' => 'Private Health Insurance'],
                ['id' => 'Cuti Tahunan', 'en' => 'Annual Leave'],
                ['id' => 'Tunjangan Transportasi', 'en' => 'Transportation Allowance'],
                ['id' => 'Tunjangan Makan', 'en' => 'Meal Allowance'],
                ['id' => 'Pelatihan & Pengembangan', 'en' => 'Training & Development'],
                ['id' => 'Jam Kerja Fleksibel', 'en' => 'Flexible Working Hours'],
                ['id' => 'Opsi Kerja Jarak Jauh', 'en' => 'Remote Work Option'],
                ['id' => 'Uang Lembur', 'en' => 'Overtime Pay'],
            ],
        );
    }
}
