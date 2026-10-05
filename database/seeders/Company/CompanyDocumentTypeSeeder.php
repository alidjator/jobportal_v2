<?php

namespace Database\Seeders\Company;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Initial document types a company can be verified with. The values and names were written for this
 * project and approved by the user (2026-10-05); admins change the list afterwards through the API.
 *
 * Rows are inserted by slug and existing rows are left untouched, so running the seeder again never
 * overwrites changes made by an admin. The slugs are explicit (not generated from the names).
 */
class CompanyDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $records = [];

        foreach ($this->rows() as $position => $row) {
            $records[] = [
                'slug' => $row['slug'],
                'name' => json_encode($row['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'is_required' => $row['is_required'],
                'is_active' => true,
                'sort_order' => $position + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('company_document_types')->insertOrIgnore($records);
    }

    /**
     * @return list<array{slug: string, name: array{id: string, en: string}, is_required: bool}>
     */
    private function rows(): array
    {
        return [
            [
                'slug' => 'business-registration',
                'name' => ['id' => 'Nomor Induk Berusaha (NIB)', 'en' => 'Business Registration Number (NIB)'],
                'is_required' => true,
            ],
            [
                'slug' => 'tax-id',
                'name' => ['id' => 'Nomor Pokok Wajib Pajak (NPWP)', 'en' => 'Tax Identification Number (NPWP)'],
                'is_required' => true,
            ],
            [
                'slug' => 'deed-of-establishment',
                'name' => ['id' => 'Akta Pendirian Perusahaan', 'en' => 'Deed of Establishment'],
                'is_required' => false,
            ],
        ];
    }
}
