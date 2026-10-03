<?php

namespace Database\Seeders\Reference;

/**
 * Nationality of a candidate. The legacy `nationalities` table is empty, so this is a short list
 * written for this project: Indonesia first, then neighbouring countries and the main countries of
 * origin of expatriate workers. English names are the demonyms, Indonesian names are the country
 * names ("kewarganegaraan"). Admins add further nationalities through the API.
 */
class NationalitySeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_nationalities';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Indonesia', 'en' => 'Indonesian']],
            ['name' => ['id' => 'Malaysia', 'en' => 'Malaysian']],
            ['name' => ['id' => 'Singapura', 'en' => 'Singaporean']],
            ['name' => ['id' => 'Filipina', 'en' => 'Filipino']],
            ['name' => ['id' => 'Thailand', 'en' => 'Thai']],
            ['name' => ['id' => 'Vietnam', 'en' => 'Vietnamese']],
            ['name' => ['id' => 'Brunei Darussalam', 'en' => 'Bruneian']],
            ['name' => ['id' => 'Timor Leste', 'en' => 'Timorese']],
            ['name' => ['id' => 'Tiongkok', 'en' => 'Chinese']],
            ['name' => ['id' => 'Jepang', 'en' => 'Japanese']],
            ['name' => ['id' => 'Korea Selatan', 'en' => 'South Korean']],
            ['name' => ['id' => 'India', 'en' => 'Indian']],
            ['name' => ['id' => 'Pakistan', 'en' => 'Pakistani']],
            ['name' => ['id' => 'Bangladesh', 'en' => 'Bangladeshi']],
            ['name' => ['id' => 'Australia', 'en' => 'Australian']],
            ['name' => ['id' => 'Amerika Serikat', 'en' => 'American']],
            ['name' => ['id' => 'Inggris', 'en' => 'British']],
            ['name' => ['id' => 'Kanada', 'en' => 'Canadian']],
            ['name' => ['id' => 'Jerman', 'en' => 'German']],
            ['name' => ['id' => 'Prancis', 'en' => 'French']],
            ['name' => ['id' => 'Belanda', 'en' => 'Dutch']],
            ['name' => ['id' => 'Arab Saudi', 'en' => 'Saudi Arabian']],
            ['name' => ['id' => 'Uni Emirat Arab', 'en' => 'Emirati']],
            ['name' => ['id' => 'Turki', 'en' => 'Turkish']],
            ['name' => ['id' => 'Rusia', 'en' => 'Russian']],
        ];
    }
}
