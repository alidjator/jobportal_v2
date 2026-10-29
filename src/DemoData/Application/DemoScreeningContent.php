<?php

namespace Src\DemoData\Application;

use Src\Recruitment\Domain\QuestionType;

/**
 * The fictitious screening questions of the verified company, how each job uses them and what each job seeker of `DemoCast::jobSeekers()` answers. The texts
 * are Indonesian, as the job seekers see them.
 */
final class DemoScreeningContent
{
    /**
     * The library by key.
     *
     * @return array<string, array{type: QuestionType, text: string, labels: list<string>}>
     */
    public static function questions(): array
    {
        return [
            'exp' => ['type' => QuestionType::Number, 'text' => 'Berapa tahun pengalaman Anda bekerja sebagai developer?', 'labels' => []],
            'stack' => ['type' => QuestionType::MultipleChoice, 'text' => 'Teknologi mana yang pernah Anda pakai di lingkungan produksi?', 'labels' => ['PHP', 'Laravel', 'PostgreSQL', 'MySQL', 'Redis', 'Docker']],
            'hybrid' => ['type' => QuestionType::YesNo, 'text' => 'Apakah Anda bersedia bekerja hibrida, dengan dua hari kerja di kantor Jakarta setiap minggu?', 'labels' => []],
            'onsite' => ['type' => QuestionType::YesNo, 'text' => 'Apakah Anda bersedia bekerja penuh di kantor kami di Jakarta?', 'labels' => []],
            'shift' => ['type' => QuestionType::YesNo, 'text' => 'Apakah Anda bersedia bekerja dengan sistem shift, termasuk akhir pekan?', 'labels' => []],
            'sql' => ['type' => QuestionType::SingleChoice, 'text' => 'Bagaimana Anda menilai kemampuan SQL Anda?', 'labels' => ['Dasar', 'Menengah', 'Mahir']],
            'tools' => ['type' => QuestionType::MultipleChoice, 'text' => 'Alat desain apa yang Anda kuasai?', 'labels' => ['Figma', 'Sketch', 'Adobe XD', 'Miro', 'Maze']],
            'notice' => ['type' => QuestionType::SingleChoice, 'text' => 'Kapan Anda dapat mulai bekerja jika diterima?', 'labels' => ['Segera', 'Dua minggu', 'Satu bulan', 'Lebih dari satu bulan']],
            'salary' => ['type' => QuestionType::Number, 'text' => 'Berapa gaji bulanan yang Anda harapkan (dalam Rupiah)?', 'labels' => []],
            'portfolio' => ['type' => QuestionType::Text, 'text' => 'Tautkan portofolio atau contoh karya terbaik Anda dan jelaskan peran Anda di dalamnya.', 'labels' => []],
            'motivation' => ['type' => QuestionType::Text, 'text' => 'Mengapa Anda tertarik pada posisi ini?', 'labels' => []],
        ];
    }

    /**
     * What a must-have question expects: `min` for a number, `yes` for a yes/no, the accepted `labels` for a choice.
     *
     * @return array<string, array{min?: int, yes?: bool, labels?: list<string>}>
     */
    public static function ideals(): array
    {
        return [
            'exp' => ['min' => 2],
            'stack' => ['labels' => ['PHP']],
            'hybrid' => ['yes' => true],
            'onsite' => ['yes' => true],
            'shift' => ['yes' => true],
            'sql' => ['labels' => ['Menengah', 'Mahir']],
        ];
    }

    /**
     * The questions of each job in their order: the question key, whether it is required and whether it is must-have. The draft job has some too.
     *
     * @return array<string, list<array{string, bool, bool}>>
     */
    public static function jobs(): array
    {
        return [
            'backend' => [['exp', true, true], ['stack', true, true], ['hybrid', true, true], ['notice', true, false], ['salary', false, false]],
            'mobile' => [['exp', true, true], ['notice', true, false], ['salary', false, false]],
            'uiux' => [['portfolio', true, false], ['tools', true, false], ['notice', true, false], ['salary', false, false]],
            'data' => [['sql', true, true], ['onsite', true, true], ['notice', true, false], ['motivation', false, false]],
            'support' => [['shift', true, true], ['onsite', true, true], ['notice', true, false], ['motivation', true, false]],
        ];
    }

    /**
     * The answers of a job seeker (index in `DemoCast::jobSeekers()`) to every question, by question key: a number, a bool, the label or the labels of the
     * choice, or a text. Some fail a must-have question on purpose, so the applications show both a pass and a fail.
     *
     * @return array<string, int|bool|string|list<string>>
     */
    public static function answers(int $seeker): array
    {
        $base = [
            'exp' => 3, 'stack' => ['PHP', 'Laravel', 'PostgreSQL'], 'hybrid' => true, 'onsite' => true, 'shift' => true, 'sql' => 'Menengah', 'tools' => ['Figma'],
            'notice' => 'Dua minggu', 'salary' => 14_000_000,
            'portfolio' => 'Portofolio saya ada di https://portofolio.demo.test/karya. Saya merancang alur pendaftaran dan dasbor untuk produk SaaS dari riset sampai prototipe.',
            'motivation' => 'Saya ingin berkembang bersama tim yang menjaga kualitas kerja dan terus belajar.',
        ];

        $changes = [
            0 => ['stack' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis']],
            1 => ['exp' => 4, 'stack' => ['MySQL', 'Docker'], 'sql' => 'Mahir', 'notice' => 'Satu bulan', 'salary' => 13_000_000],
            2 => ['notice' => 'Segera', 'salary' => 12_000_000],
            3 => ['exp' => 6, 'stack' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker'], 'notice' => 'Satu bulan', 'salary' => 18_000_000],
            4 => ['exp' => 4, 'salary' => 15_000_000],
            5 => ['exp' => 0, 'stack' => ['PHP'], 'sql' => 'Dasar', 'notice' => 'Segera', 'salary' => 6_000_000],
            6 => ['tools' => ['Figma', 'Miro', 'Maze'], 'notice' => 'Segera', 'salary' => 11_000_000],
            7 => ['sql' => 'Mahir', 'onsite' => false, 'salary' => 12_500_000],
        ];

        return [...$base, ...($changes[$seeker] ?? [])];
    }
}
