<?php

namespace Database\Seeders\Reference;

/**
 * Religion of a candidate. The six values and their order come from the legacy master data, whose
 * "English" names are Indonesian words with some non-standard spellings (Budha, Konghuchu). Here the
 * Indonesian names use the standard spelling and the English names are English terms, so the slugs
 * differ from the legacy ones. The names were written for this project.
 */
class ReligionSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_religions';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Islam', 'en' => 'Islam']],
            ['name' => ['id' => 'Katolik', 'en' => 'Catholic']],
            ['name' => ['id' => 'Protestan', 'en' => 'Protestant']],
            ['name' => ['id' => 'Buddha', 'en' => 'Buddhism']],
            ['name' => ['id' => 'Hindu', 'en' => 'Hindu']],
            ['name' => ['id' => 'Konghucu', 'en' => 'Confucianism']],
        ];
    }
}
