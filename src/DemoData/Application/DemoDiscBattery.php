<?php

namespace Src\DemoData\Application;

use Src\Assessment\Domain\MostLeast;
use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionFormat;

/**
 * A small battery of the DISC family to try the whole path (`docs/design/phase-8-disc-profile.md` D1): six groups of four statements, one per dimension, a norm table for the twelve scales, a report and an explanation
 * template with the profile placeholders. **Every statement and every norm is fictitious** (the statements say so): the platform ships no instrument content, and a partner writes its own battery.
 */
final class DemoDiscBattery
{
    /** The four dimensions and what a demo reader is told about each (neutral words, not a published description). */
    private const array DIMENSIONS = [
        'd' => ['Dominan', 'dorongan untuk memimpin dan mengambil keputusan'],
        'i' => ['Pengaruh', 'kecenderungan untuk mengajak dan bergaul'],
        's' => ['Stabil', 'kecenderungan untuk tenang dan konsisten'],
        'c' => ['Teliti', 'kecenderungan untuk cermat dan mengikuti aturan'],
    ];

    private const int GROUPS = 6;

    /** The three graphs: their names, and what a high score in each tells a demo reader. */
    private const array GRAPHS = [
        'most' => ['Paling', 'yang paling sering Anda pilih sebagai paling sesuai dengan diri Anda'],
        'least' => ['Paling tidak', 'yang paling sering Anda pilih sebagai paling tidak sesuai dengan diri Anda'],
        'change' => ['Perubahan', 'selisih antara pilihan paling dan paling tidak'],
    ];

    /**
     * @return array{title: string, description: string, instructions: string, capabilities: list<string>, sections: list<array<string, mixed>>, norms: list<Norm>, report: string, explanation: string, key_scales: list<string>, recommended_min: float, not_yet_below: float}
     */
    public static function battery(): array
    {
        return [
            'title' => 'Profil Perilaku DISC (contoh fiktif)',
            'description' => 'Contoh baterai keluarga DISC dengan pernyataan dan norma fiktif, untuk mencoba seluruh alurnya. Penyedia menulis baterainya sendiri.',
            'instructions' => 'Di setiap kelompok ada empat pernyataan. Tandai satu yang paling sesuai dengan Anda dan satu yang paling tidak sesuai. Tidak ada jawaban benar atau salah. Semua pernyataan di sini fiktif.',
            'capabilities' => ['Gaya perilaku kerja'],
            'sections' => [[
                'title' => 'Kelompok pernyataan', 'instructions' => 'Tandai satu pernyataan paling sesuai dan satu paling tidak sesuai di setiap kelompok.',
                'format' => SectionFormat::Questions, 'minutes' => 15, 'settings' => [], 'questions' => self::questions(),
            ]],
            'norms' => self::norms(),
            'report' => self::report(),
            'explanation' => self::explanation(),
            'key_scales' => ['d_most', 'i_most'],
            'recommended_min' => 55.0,
            'not_yet_below' => 45.0,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function questions(): array
    {
        $dimensions = array_keys(self::DIMENSIONS);
        $questions = [];

        for ($group = 1; $group <= self::GROUPS; $group++) {
            $options = [];

            // The dimensions rotate from group to group, so the same position is not always the same dimension.
            foreach (array_keys($dimensions) as $index) {
                $dimension = $dimensions[($index + $group) % count($dimensions)];
                $options[] = ["Contoh pernyataan fiktif {$group}{$dimension} (bukan butir instrumen sungguhan)", false, null, $dimension];
            }

            $questions[] = ['type' => QuestionType::MostLeast, 'text' => "Kelompok {$group}: tandai yang paling dan yang paling tidak sesuai dengan Anda.", 'scale' => null, 'options' => $options];
        }

        return $questions;
    }

    /**
     * The norm table of the twelve scales: a count of 0 or 1 is low (40), 2 or 3 is middle (50) and 4 to 6 is high (60); the change is low up to -2, middle from -1 to 1 and high from 2. All fictitious.
     *
     * @return list<Norm>
     */
    private static function norms(): array
    {
        $norms = [];

        foreach (self::DIMENSIONS as $dimension => [$label, $about]) {
            foreach (self::GRAPHS as $graph => [$graphName, $graphAbout]) {
                $ranges = $graph === 'change'
                    ? [[-self::GROUPS, -2, 40.0, 'Rendah'], [-1, 1, 50.0, 'Sedang'], [2, self::GROUPS, 60.0, 'Tinggi']]
                    : [[0, 1, 40.0, 'Rendah'], [2, 3, 50.0, 'Sedang'], [4, self::GROUPS, 60.0, 'Tinggi']];

                foreach ($ranges as [$from, $to, $standard, $band]) {
                    $norms[] = new Norm(MostLeast::key($dimension, $graph), $from, $to, $standard, $band, "{$label} ({$graphName}): skor Anda tergolong {$band}. Ini menggambarkan {$about}, {$graphAbout}. Penjelasan ini fiktif, hanya untuk contoh.");
                }
            }
        }

        return $norms;
    }

    private static function report(): string
    {
        $lines = [
            "LAPORAN PROFIL PERILAKU (CONTOH FIKTIF)\nNomor laporan: {{report_number}}\n",
            "Nama peserta: {{candidate_name}}\nPosisi yang dilamar: {{job_title}}\nInstrumen: {{test_title}}\n",
            "PROFIL\nDimensi tinggi pada grafik Paling: {{profile:most}}\nDimensi tinggi pada grafik Paling tidak: {{profile:least}}\nDimensi tinggi pada grafik Perubahan: {{profile:change}}\n",
            'HASIL PER DIMENSI',
        ];

        foreach (self::DIMENSIONS as $dimension => [$label]) {
            $lines[] = "- {$label}: Paling {{scale:{$dimension}_most:raw}} (skor standar {{scale:{$dimension}_most:standard}}), Paling tidak {{scale:{$dimension}_least:raw}} (skor standar {{scale:{$dimension}_least:standard}}), Perubahan {{scale:{$dimension}_change:raw}} (skor standar {{scale:{$dimension}_change:standard}})";
        }

        $lines[] = "\nREKOMENDASI\n{{recommendation}}\n\nSeluruh isi laporan ini adalah contoh fiktif dan tidak boleh dipakai untuk keputusan sungguhan.";
        $lines[] = "\nDitandatangani pada {{signed_on}} oleh {{psychologist_name}}.";

        return implode("\n", $lines);
    }

    private static function explanation(): string
    {
        $lines = ["Yth. {{candidate_name}},\n\nBerikut penjelasan hasil {{test_title}} yang Anda kerjakan. Isinya contoh fiktif untuk mencoba alurnya.\n"];

        foreach (array_keys(self::DIMENSIONS) as $dimension) {
            foreach (array_keys(self::GRAPHS) as $graph) {
                $lines[] = '- {{scale:'.MostLeast::key($dimension, $graph).':explanation}}';
            }
        }

        $lines[] = "\nLaporan nomor {{report_number}} ditandatangani oleh {{psychologist_name}} pada {{signed_on}}.";

        return implode("\n", $lines);
    }
}
