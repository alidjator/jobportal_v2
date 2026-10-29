<?php

namespace Src\DemoData\Application;

use Src\Recruitment\Domain\QuestionType;

/**
 * The screening presets of the demo ({@see DemoPresets}): named sets of library questions with their flags, which a recruiter applies to a job in one step. The verified company uses the questions of
 * {@see DemoScreeningContent} and a few more; the second company has a small library of its own. The texts are Indonesian, as the job seekers see them. A preset keeps to the limit of a job's list (`recruitment.max_screening_questions`, 5 by default).
 */
final class DemoPresetContent
{
    /**
     * The questions the presets need beyond `DemoScreeningContent::questions()`, by key and by company key; a question whose text is already in the library of the company is reused.
     *
     * @return array<string, array<string, array{type: QuestionType, text: string, labels: list<string>}>>
     */
    public static function extraQuestions(): array
    {
        return [
            DemoCast::SINAR => [
                'domicile' => ['type' => QuestionType::YesNo, 'text' => 'Apakah Anda berdomisili di Jabodetabek atau bersedia pindah ke sana?', 'labels' => []],
                'education' => ['type' => QuestionType::SingleChoice, 'text' => 'Apa pendidikan terakhir Anda?', 'labels' => ['SMA atau SMK', 'Diploma', 'Sarjana', 'Magister']],
                'english' => ['type' => QuestionType::SingleChoice, 'text' => 'Bagaimana kemampuan bahasa Inggris Anda dalam pekerjaan?', 'labels' => ['Pasif', 'Aktif', 'Profesional']],
                'leadership' => ['type' => QuestionType::Number, 'text' => 'Berapa orang yang pernah Anda pimpin secara langsung?', 'labels' => []],
            ],
            DemoCast::ARUNIKA => [
                'shift' => ['type' => QuestionType::YesNo, 'text' => 'Apakah Anda bersedia bekerja dengan sistem shift, termasuk akhir pekan dan hari libur?', 'labels' => []],
                'retail' => ['type' => QuestionType::Number, 'text' => 'Berapa tahun pengalaman Anda di bidang ritel?', 'labels' => []],
                'city' => ['type' => QuestionType::SingleChoice, 'text' => 'Di kota mana Anda bersedia ditempatkan?', 'labels' => ['Jakarta', 'Bandung', 'Surabaya', 'Mana saja']],
                'notice' => ['type' => QuestionType::SingleChoice, 'text' => 'Kapan Anda dapat mulai bekerja jika diterima?', 'labels' => ['Segera', 'Dua minggu', 'Satu bulan', 'Lebih dari satu bulan']],
                'motivation' => ['type' => QuestionType::Text, 'text' => 'Mengapa Anda tertarik bekerja di toko kami?', 'labels' => []],
            ],
        ];
    }

    /**
     * What a must-have of the extra questions expects, like {@see DemoScreeningContent::ideals()}.
     *
     * @return array<string, array<string, array{min?: int, yes?: bool, labels?: list<string>}>>
     */
    public static function extraIdeals(): array
    {
        return [
            DemoCast::SINAR => ['domicile' => ['yes' => true], 'education' => ['labels' => ['Sarjana', 'Magister']], 'english' => ['labels' => ['Aktif', 'Profesional']], 'leadership' => ['min' => 3]],
            DemoCast::ARUNIKA => ['shift' => ['yes' => true], 'retail' => ['min' => 1], 'city' => ['labels' => ['Jakarta', 'Mana saja']]],
        ];
    }

    /**
     * The presets of each company: the name, the job level (`junior`, `middle`, `senior` of the reference data, or null for any) and the questions in their order as `[key, required, must-have]`. A key is in
     * `DemoScreeningContent::questions()` or in `extraQuestions()` of the company.
     *
     * @return array<string, list<array{name: string, level: string|null, items: list<array{string, bool, bool}>}>>
     */
    public static function presets(): array
    {
        return [
            DemoCast::SINAR => [
                [
                    'name' => 'Engineer backend', 'level' => 'middle',
                    'items' => [['exp', true, true], ['stack', true, true], ['hybrid', true, true], ['english', true, false], ['notice', true, false]],
                ],
                [
                    'name' => 'Engineer lulusan baru', 'level' => 'junior',
                    'items' => [['education', true, true], ['stack', true, false], ['domicile', true, true], ['notice', true, false], ['motivation', true, false]],
                ],
                [
                    'name' => 'Desainer produk', 'level' => null,
                    'items' => [['portfolio', true, false], ['tools', true, false], ['english', false, false], ['notice', true, false], ['salary', false, false]],
                ],
                [
                    'name' => 'Analis data', 'level' => null,
                    'items' => [['sql', true, true], ['onsite', true, true], ['english', true, false], ['notice', true, false], ['motivation', false, false]],
                ],
                [
                    'name' => 'Layanan pelanggan dan operasional', 'level' => 'junior',
                    'items' => [['shift', true, true], ['onsite', true, true], ['domicile', true, true], ['notice', true, false], ['motivation', true, false]],
                ],
                [
                    'name' => 'Manajer dan kepala divisi', 'level' => 'senior',
                    'items' => [['exp', true, true], ['leadership', true, true], ['english', true, true], ['notice', true, false], ['salary', true, false]],
                ],
                [
                    'name' => 'Semua posisi (ringkas)', 'level' => null,
                    'items' => [['notice', true, false], ['salary', false, false], ['motivation', false, false]],
                ],
            ],
            DemoCast::ARUNIKA => [
                [
                    'name' => 'Staf toko', 'level' => 'junior',
                    'items' => [['shift', true, true], ['city', true, true], ['notice', true, false], ['motivation', false, false]],
                ],
                [
                    'name' => 'Supervisor dan manajer toko', 'level' => 'middle',
                    'items' => [['retail', true, true], ['shift', true, true], ['city', true, false], ['notice', true, false]],
                ],
            ],
        ];
    }
}
