<?php

namespace Database\Seeders\Reference;

/**
 * Languages a candidate can speak. The legacy `candidate_languages` table holds the full ISO 639-1
 * list of 183 names (with one duplicate and joined synonyms); only the languages relevant to the
 * Indonesian job market are seeded and admins add more through the API. The legacy table stores no
 * language codes, so the ISO 639-1 codes and the Indonesian names were written for this project.
 */
class LanguageSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_languages';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Indonesia', 'en' => 'Indonesian'], 'extra' => ['code' => 'id']],
            ['name' => ['id' => 'Inggris', 'en' => 'English'], 'extra' => ['code' => 'en']],
            ['name' => ['id' => 'Tionghoa (Mandarin)', 'en' => 'Chinese'], 'extra' => ['code' => 'zh']],
            ['name' => ['id' => 'Arab', 'en' => 'Arabic'], 'extra' => ['code' => 'ar']],
            ['name' => ['id' => 'Jepang', 'en' => 'Japanese'], 'extra' => ['code' => 'ja']],
            ['name' => ['id' => 'Korea', 'en' => 'Korean'], 'extra' => ['code' => 'ko']],
            ['name' => ['id' => 'Jawa', 'en' => 'Javanese'], 'extra' => ['code' => 'jv']],
            ['name' => ['id' => 'Sunda', 'en' => 'Sundanese'], 'extra' => ['code' => 'su']],
            ['name' => ['id' => 'Melayu', 'en' => 'Malay'], 'extra' => ['code' => 'ms']],
            ['name' => ['id' => 'Prancis', 'en' => 'French'], 'extra' => ['code' => 'fr']],
            ['name' => ['id' => 'Jerman', 'en' => 'German'], 'extra' => ['code' => 'de']],
            ['name' => ['id' => 'Belanda', 'en' => 'Dutch'], 'extra' => ['code' => 'nl']],
            ['name' => ['id' => 'Spanyol', 'en' => 'Spanish'], 'extra' => ['code' => 'es']],
            ['name' => ['id' => 'Portugis', 'en' => 'Portuguese'], 'extra' => ['code' => 'pt']],
            ['name' => ['id' => 'Rusia', 'en' => 'Russian'], 'extra' => ['code' => 'ru']],
            ['name' => ['id' => 'Italia', 'en' => 'Italian'], 'extra' => ['code' => 'it']],
            ['name' => ['id' => 'Hindi', 'en' => 'Hindi'], 'extra' => ['code' => 'hi']],
            ['name' => ['id' => 'Tamil', 'en' => 'Tamil'], 'extra' => ['code' => 'ta']],
            ['name' => ['id' => 'Thai', 'en' => 'Thai'], 'extra' => ['code' => 'th']],
            ['name' => ['id' => 'Vietnam', 'en' => 'Vietnamese'], 'extra' => ['code' => 'vi']],
            ['name' => ['id' => 'Turki', 'en' => 'Turkish'], 'extra' => ['code' => 'tr']],
            ['name' => ['id' => 'Urdu', 'en' => 'Urdu'], 'extra' => ['code' => 'ur']],
            ['name' => ['id' => 'Bengali', 'en' => 'Bengali'], 'extra' => ['code' => 'bn']],
            ['name' => ['id' => 'Tagalog', 'en' => 'Tagalog'], 'extra' => ['code' => 'tl']],
            ['name' => ['id' => 'Persia', 'en' => 'Persian'], 'extra' => ['code' => 'fa']],
        ];
    }
}
