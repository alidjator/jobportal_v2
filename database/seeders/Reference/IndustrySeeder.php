<?php

namespace Database\Seeders\Reference;

/**
 * Industry of a company. The 25 values and their order come from the legacy `industry_types` table
 * (a template default list). The English names were tidied for this project: a typo ("Mon-Bank"),
 * abbreviations (Archi/Enggr, Govt., Mgt.) and slashes were replaced, so the generated slugs read
 * well. The legacy table stores no slugs. The Indonesian names were written for this project.
 */
class IndustrySeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_industries';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Industri Berbasis Agro', 'en' => 'Agro-Based Industry']],
            ['name' => ['id' => 'Arsitektur, Teknik & Konstruksi', 'en' => 'Architecture, Engineering & Construction']],
            ['name' => ['id' => 'Otomotif & Mesin Industri', 'en' => 'Automobile & Industrial Machinery']],
            ['name' => ['id' => 'Bank & Lembaga Keuangan Non-Bank', 'en' => 'Banks & Non-Bank Financial Institutions']],
            ['name' => ['id' => 'Elektronik & Barang Konsumsi Tahan Lama', 'en' => 'Electronics & Consumer Durables']],
            ['name' => ['id' => 'Energi, Listrik & Bahan Bakar', 'en' => 'Energy, Power & Fuel']],
            ['name' => ['id' => 'Garmen & Tekstil', 'en' => 'Garments & Textiles']],
            ['name' => ['id' => 'Pemerintah, Semi Pemerintah & Lembaga Otonom', 'en' => 'Government, Semi-Government & Autonomous Bodies']],
            ['name' => ['id' => 'Farmasi', 'en' => 'Pharmaceuticals']],
            ['name' => ['id' => 'Rumah Sakit & Pusat Diagnostik', 'en' => 'Hospitals & Diagnostic Centers']],
            ['name' => ['id' => 'Maskapai, Perjalanan & Pariwisata', 'en' => 'Airlines, Travel & Tourism']],
            ['name' => ['id' => 'Manufaktur (Industri Ringan)', 'en' => 'Manufacturing (Light Industry)']],
            ['name' => ['id' => 'Manufaktur (Industri Berat)', 'en' => 'Manufacturing (Heavy Industry)']],
            ['name' => ['id' => 'Hotel & Restoran', 'en' => 'Hotels & Restaurants']],
            ['name' => ['id' => 'Teknologi Informasi', 'en' => 'Information Technology']],
            ['name' => ['id' => 'Logistik & Transportasi', 'en' => 'Logistics & Transportation']],
            ['name' => ['id' => 'Hiburan & Rekreasi', 'en' => 'Entertainment & Recreation']],
            ['name' => ['id' => 'Media, Periklanan & Manajemen Acara', 'en' => 'Media, Advertising & Event Management']],
            ['name' => ['id' => 'LSM & Pembangunan', 'en' => 'NGOs & Development']],
            ['name' => ['id' => 'Real Estat & Pengembangan Properti', 'en' => 'Real Estate & Development']],
            ['name' => ['id' => 'Grosir, Ritel & Ekspor-Impor', 'en' => 'Wholesale, Retail & Export-Import']],
            ['name' => ['id' => 'Telekomunikasi', 'en' => 'Telecommunications']],
            ['name' => ['id' => 'Industri Makanan & Minuman', 'en' => 'Food & Beverage Industry']],
            ['name' => ['id' => 'Jasa Keamanan', 'en' => 'Security Services']],
            ['name' => ['id' => 'Kebakaran, Keselamatan & Perlindungan', 'en' => 'Fire, Safety & Protection']],
        ];
    }
}
