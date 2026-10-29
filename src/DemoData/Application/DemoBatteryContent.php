<?php

namespace Src\DemoData\Application;

use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionFormat;

/**
 * The psychological batteries of the demo provider, modelled on the batteries the benchmark describes for the local partner (Professional Talent
 * Assessment, Leadership Competency Profile, Operational Readiness Test; `docs/analysis/benchmark-teleinterview-assessment.md` section 3.3): intelligence
 * subtests of the IST kind, Kraepelin-type speed columns and work-attitude or leadership Likert scales. Each battery comes in two sizes: a short version
 * (a few items per subtest) and a full one with the question count and time the benchmark names (570 questions and 150 minutes, 570 and 150, 420 and 90;
 * the speed columns are not counted as questions). The items are original, written or generated for the demo (`DemoPsychometricBanks`), with the norm
 * table, report text, explanation and recommendation rule each battery needs to be published.
 *
 * A question is `{type, text, scale, options: [[label, correct, score]]}`; a section is `{title, instructions, format, minutes, settings, questions}`.
 */
final class DemoBatteryContent
{
    private const array LIKERT = [['Sangat tidak setuju', 1], ['Tidak setuju', 2], ['Netral', 3], ['Setuju', 4], ['Sangat setuju', 5]];

    private const array BANDS = [['Sangat rendah', 70.0, 'jauh di bawah rata-rata kelompok pembanding, sehingga area ini memerlukan pengembangan dan pendampingan yang jelas'], ['Rendah', 85.0, 'di bawah rata-rata kelompok pembanding'], ['Sedang', 100.0, 'setara dengan rata-rata kelompok pembanding'], ['Tinggi', 115.0, 'di atas rata-rata kelompok pembanding'], ['Sangat tinggi', 130.0, 'jauh di atas rata-rata kelompok pembanding']];

    /** @return list<string> the short batteries, and the full-size ones when asked for */
    public static function keys(bool $full = false): array
    {
        $short = ['professional', 'leadership', 'operational'];

        return $full ? [...$short, ...array_map(static fn (string $key): string => $key.'_full', $short)] : $short;
    }

    /**
     * @return array{title: string, description: string, instructions: string, capabilities: list<string>, sections: list<array<string, mixed>>, norms: list<Norm>, report: string, explanation: string, key_scales: list<string>, recommended_min: float, not_yet_below: float}
     */
    public static function battery(string $key): array
    {
        $full = str_ends_with($key, '_full');
        $plan = match (str_replace('_full', '', $key)) {
            'professional' => self::professional($full),
            'leadership' => self::leadership($full),
            default => self::operational($full),
        };

        $scales = self::scalesOf($plan['sections']);

        return [
            'title' => $plan['title'].($full ? ' (versi penuh)' : ' (versi ringkas)'),
            'description' => $plan['description'].($full ? ' Versi penuh: '.$plan['size'].'.' : ' Versi ringkas dengan butir buatan demo, bergaya subtes IST.'),
            'instructions' => 'Kerjakan sendiri di tempat yang tenang, tanpa membuka aplikasi lain atau memakai dua perangkat sekaligus. Setiap bagian punya batas waktu sendiri dan tes terkunci setelah dikirim. Hasilnya dibaca oleh psikolog dan dijelaskan kepada Anda.',
            'capabilities' => $plan['capabilities'],
            'sections' => $plan['sections'],
            'norms' => self::norms($scales),
            'report' => self::report($scales),
            'explanation' => self::explanation($scales),
            'key_scales' => $plan['key_scales'],
            'recommended_min' => 100.0,
            'not_yet_below' => 85.0,
        ];
    }

    /** @return array<string, mixed> */
    private static function professional(bool $full): array
    {
        $n = $full ? [50, 40, 50, 40] : [6, 6, 6, 4];

        return [
            'title' => 'Professional Talent Assessment',
            'description' => 'Baterai untuk menilai bakat profesional: kemampuan verbal, numerik dan penalaran, kecepatan serta ketahanan kerja (kolom Kraepelin) dan sikap kerja.',
            'size' => '570 soal, 150 menit',
            'capabilities' => ['Kemampuan verbal', 'Kemampuan numerik', 'Penalaran logis', 'Kecepatan dan ketahanan kerja', 'Sikap kerja'],
            'sections' => [
                self::questions('Bagian 1: Kemampuan verbal', 'Kerjakan soal verbal berikut. Pilih satu jawaban yang paling tepat.', $full ? 45 : 20, ['verbal_se' => $n[0], 'verbal_wa' => $n[0], 'verbal_an' => $n[0], 'verbal_ge' => $n[0]]),
                self::questions('Bagian 2: Kemampuan numerik', 'Tulis jawaban berupa angka saja, tanpa satuan.', $full ? 30 : 15, ['numeric_ra' => $n[1], 'numeric_zr' => $n[1]]),
                self::questions('Bagian 3: Penalaran logis', 'Pilih jawaban yang paling tepat berdasarkan keterangan yang diberikan saja.', $full ? 15 : 8, ['abstract_lg' => $n[2]]),
                self::columns('Bagian 4: Kolom hitung', $full ? 20 : 10),
                self::questions('Bagian 5: Sikap kerja (1)', 'Tidak ada jawaban benar atau salah. Pilih yang paling menggambarkan diri Anda.', $full ? 28 : 5, ['accuracy' => $n[3], 'endurance' => $n[3], 'pace' => $n[3]]),
                self::questions('Bagian 6: Sikap kerja (2)', 'Tidak ada jawaban benar atau salah. Pilih yang paling menggambarkan diri Anda.', $full ? 27 : 5, ['initiative' => $n[3], 'teamwork' => $n[3], 'discipline' => $n[3]]),
            ],
            'key_scales' => ['verbal_an', 'numeric_ra', 'numeric_zr', 'accuracy', 'discipline'],
        ];
    }

    /** @return array<string, mixed> */
    private static function leadership(bool $full): array
    {
        $n = $full ? [60, 50, 50, 50] : [6, 6, 6, 4];

        return [
            'title' => 'Leadership Competency Profile',
            'description' => 'Baterai untuk menilai potensi kepemimpinan: penalaran dasar dan enam kompetensi kepemimpinan.',
            'size' => '570 soal, 150 menit',
            'capabilities' => ['Penalaran verbal dan numerik', 'Penalaran logis', 'Pengambilan keputusan', 'Pengaruh dan komunikasi', 'Orientasi hasil', 'Integritas'],
            'sections' => [
                self::questions('Bagian 1: Penalaran verbal', 'Pilih satu jawaban yang paling tepat.', $full ? 30 : 12, ['verbal_an' => $n[0], 'verbal_ge' => $n[0]]),
                self::questions('Bagian 2: Penalaran numerik', 'Tulis jawaban berupa angka saja, tanpa satuan.', $full ? 28 : 15, ['numeric_ra' => $n[1], 'numeric_zr' => $n[1]]),
                self::questions('Bagian 3: Penalaran logis', 'Pilih jawaban yang paling tepat berdasarkan keterangan yang diberikan saja.', $full ? 12 : 8, ['abstract_lg' => $n[2]]),
                self::questions('Bagian 4: Profil kepemimpinan (1)', 'Tidak ada jawaban benar atau salah. Pilih yang paling menggambarkan diri Anda dalam bekerja dengan tim.', $full ? 40 : 8, ['decision' => $n[3], 'influence' => $n[3], 'drive' => $n[3]]),
                self::questions('Bagian 5: Profil kepemimpinan (2)', 'Tidak ada jawaban benar atau salah. Pilih yang paling menggambarkan diri Anda dalam bekerja dengan tim.', $full ? 40 : 8, ['strategic' => $n[3], 'develop' => $n[3], 'integrity' => $n[3]]),
            ],
            'key_scales' => ['verbal_an', 'numeric_ra', 'decision', 'drive', 'integrity'],
        ];
    }

    /** @return array<string, mixed> */
    private static function operational(bool $full): array
    {
        $n = $full ? [50, 40, 60, 30] : [6, 6, 10, 4];

        return [
            'title' => 'Operational Readiness Test',
            'description' => 'Baterai untuk kesiapan kerja operasional: kemampuan dasar, ketelitian memeriksa, kolom Kraepelin dan sikap kerja termasuk keselamatan.',
            'size' => '420 soal, 90 menit',
            'capabilities' => ['Kemampuan dasar', 'Ketelitian memeriksa', 'Kecepatan dan ketahanan kerja', 'Disiplin', 'Keselamatan kerja'],
            'sections' => [
                self::questions('Bagian 1: Kemampuan verbal dasar', 'Pilih satu jawaban yang paling tepat.', $full ? 20 : 8, ['verbal_se' => $n[0], 'verbal_wa' => $n[0]]),
                self::questions('Bagian 2: Kemampuan numerik dasar', 'Tulis jawaban berupa angka saja, tanpa satuan.', $full ? 18 : 12, ['numeric_ra' => $n[1], 'numeric_zr' => $n[1]]),
                self::questions('Bagian 3: Ketelitian memeriksa', 'Bandingkan kedua kode, lalu tentukan apakah keduanya sama persis.', $full ? 10 : 6, ['checking' => $n[2]]),
                self::columns('Bagian 4: Kolom hitung', $full ? 20 : 10),
                self::questions('Bagian 5: Sikap kerja', 'Tidak ada jawaban benar atau salah. Pilih yang paling menggambarkan diri Anda.', $full ? 37 : 10, ['accuracy' => $n[3], 'endurance' => $n[3], 'pace' => $n[3], 'discipline' => $n[3], 'teamwork' => $n[3], 'safety' => $n[3]]),
            ],
            'key_scales' => ['checking', 'accuracy', 'discipline', 'endurance'],
        ];
    }

    /**
     * @param  array<string, int>  $counts  the number of questions of each scale
     * @return array<string, mixed>
     */
    private static function questions(string $title, string $instructions, int $minutes, array $counts): array
    {
        $questions = [];

        foreach ($counts as $scale => $count) {
            $attitude = DemoPsychometricBanks::scales()[$scale]['kind'] === 'attitude';

            foreach ($attitude ? self::likert($scale, $count) : self::items($scale, $count) as $question) {
                $questions[] = $question;
            }
        }

        return ['title' => $title, 'instructions' => $instructions, 'format' => SectionFormat::Questions, 'minutes' => $minutes, 'settings' => [], 'questions' => $questions];
    }

    /** @return array<string, mixed> */
    private static function columns(string $title, int $columns): array
    {
        return [
            'title' => $title,
            'instructions' => 'Jumlahkan dua angka yang berdampingan dan tulis angka satuan hasilnya. Kerjakan secepat dan seteliti mungkin; lajur berganti otomatis.',
            'format' => SectionFormat::SpeedColumns, 'minutes' => null, 'settings' => ['columns' => $columns, 'digits_per_column' => 40, 'seconds_per_column' => 15], 'questions' => [],
        ];
    }

    /**
     * The statements of an attitude scale: the four written ones first, then statements made from the situations and behaviours of the bank, a reversed
     * statement for each behaviour that goes against the scale.
     *
     * @return list<array<string, mixed>>
     */
    private static function likert(string $scale, int $count): array
    {
        $statements = DemoPsychometricBanks::statements()[$scale];
        [$fitting, $against] = DemoPsychometricBanks::behaviours()[$scale];
        $situations = DemoPsychometricBanks::situations();
        $behaviours = [...$fitting, ...$against];

        for ($g = 0; count($statements) < $count; $g++) {
            $b = $g % 8;
            $statements[] = [$situations[(intdiv($g, 8) + $b) % count($situations)].' saya '.$behaviours[$b].'.', $b >= 4];
        }

        $questions = [];

        foreach (array_slice($statements, 0, $count) as [$text, $reversed]) {
            $questions[] = [
                'type' => QuestionType::Likert, 'text' => $text, 'scale' => $scale,
                'options' => array_map(static fn (array $option): array => [$option[0], false, $reversed ? 6 - $option[1] : $option[1]], self::LIKERT),
            ];
        }

        return $questions;
    }

    /** @return list<array<string, mixed>> */
    private static function items(string $scale, int $count): array
    {
        $questions = [];

        for ($i = 0; $i < $count; $i++) {
            $questions[] = match ($scale) {
                'verbal_se' => self::sentence($i),
                'verbal_wa' => self::oddOneOut($i),
                'verbal_an' => self::analogy($i),
                'verbal_ge' => self::similarity($i),
                'numeric_ra' => self::arithmetic($i),
                'numeric_zr' => self::series($i),
                'abstract_lg' => self::logic($i),
                default => self::checking($i),
            } + ['scale' => $scale];
        }

        return $questions;
    }

    /** The written sentences first, then one sentence for each pair of the analogy bank (the relation tells what completes it). @return array<string, mixed> */
    private static function sentence(int $i): array
    {
        $sentences = DemoPsychometricBanks::sentences();

        if ($i < count($sentences)) {
            [$text, $right, $a, $b, $c] = $sentences[$i];

            return self::choice('Pilih kata yang paling tepat untuk melengkapi kalimat berikut: '.$text, $right, [$a, $b, $c], $i);
        }

        $relations = DemoPsychometricBanks::analogies();
        $j = $i - count($sentences);
        $relation = $j % 5;
        $pair = intdiv($j, 5) % 8;
        [$first, $second] = $relations[$relation][$pair];
        $text = match ($relation) {
            0 => "{$first} dipakai untuk ___.",
            1 => "{$first} adalah bagian dari ___.",
            2 => "{$first} termasuk golongan ___.",
            3 => "Lawan kata {$first} adalah ___.",
            default => "Seorang {$first} bekerja di ___.",
        };
        $wrong = [];

        foreach ([1, 2, 3, 4] as $step) {
            $wrong[] = $relations[($relation + $step) % 5][($pair + $step) % 8][1];
        }

        return self::choice('Pilih kata yang paling tepat untuk melengkapi kalimat berikut: '.$text, $second, $wrong, $i);
    }

    /** @return array<string, mixed> */
    private static function oddOneOut(int $i): array
    {
        $categories = DemoPsychometricBanks::categories();
        $names = array_keys($categories);
        $round = intdiv($i, count($names));
        $same = $categories[$names[$i % count($names)]];
        $other = $categories[$names[($i + 3 + $round) % count($names)]];
        $odd = $other[($i + $round) % 6];
        $group = [$same[$round % 6], $same[($round + 1) % 6], $same[($round + 2) % 6]];

        return self::choice('Manakah kata yang tidak sekelompok dengan ketiga kata lainnya?', $odd, $group, $i);
    }

    /** Every ordered pair (an example and an asked one) of a relation gives a different item. @return array<string, mixed> */
    private static function analogy(int $i): array
    {
        $relations = DemoPsychometricBanks::analogies();
        $relation = $i % 5;
        $k = intdiv($i, 5);
        $pair = $k % 8;
        $other = ($pair + 1 + intdiv($k, 8)) % 8;
        $example = $relations[$relation][$pair];
        $asked = $relations[$relation][$other];
        $wrong = [];

        foreach ([1, 2, 3, 4] as $step) {
            $wrong[] = $relations[($relation + $step) % 5][($other + $step) % 8][1];
        }

        return self::choice("{$example[0]} : {$example[1]} = {$asked[0]} : ?", $asked[1], $wrong, $i);
    }

    /** Every pair of members of a category gives a different item. @return array<string, mixed> */
    private static function similarity(int $i): array
    {
        $categories = DemoPsychometricBanks::categories();
        $names = array_keys($categories);
        $name = $names[$i % count($names)];
        $pairs = [[0, 1], [0, 2], [0, 3], [0, 4], [0, 5], [1, 2], [1, 3], [1, 4], [1, 5], [2, 3], [2, 4], [2, 5], [3, 4], [3, 5], [4, 5]];
        [$a, $b] = $pairs[intdiv($i, count($names)) % count($pairs)];
        $wrong = [];

        foreach ([1, 2, 3, 4] as $step) {
            $wrong[] = $names[($i + $step * 3) % count($names)];
        }

        return self::choice('Apa kesamaan antara '.$categories[$name][$a].' dan '.$categories[$name][$b].'? Keduanya termasuk…', $name, $wrong, $i);
    }

    /** @return array<string, mixed> */
    private static function arithmetic(int $i): array
    {
        $k = intdiv($i, 6);
        $a = 12 + $i * 2;
        $d = 3 + $k % 7;
        $price = (5 + $k % 9) * 1000;
        $count = 3 + $k % 6;
        $speed = 40 + 5 * ($k % 9);
        $hours = 2 + intdiv($k, 2) % 4;
        $total = 40 + 20 * ($k % 6);
        $percent = [25, 50, 10, 20, 40, 75][$k % 6];
        $days = 6 * (1 + $k % 3);
        $workers = [6, 8, 12][intdiv($k, 3) % 3];
        $apples = 5 + $k % 3;
        $each = (2 + intdiv($k, 3) % 4) * 1000;

        [$text, $answer] = match ($i % 6) {
            0 => ["Sebuah toko menjual {$a} kg beras setiap hari. Berapa kg beras terjual dalam {$d} hari?", $a * $d],
            1 => ["Harga satu buku Rp{$price}. Berapa Rupiah harga {$count} buku?", $price * $count],
            2 => ["Sebuah kendaraan melaju {$speed} km per jam selama {$hours} jam. Berapa km jarak yang ditempuh?", $speed * $hours],
            3 => ["Dari {$total} siswa, {$percent}% lulus ujian. Berapa siswa yang lulus?", intdiv($total * $percent, 100)],
            4 => ["Empat pekerja menyelesaikan suatu pekerjaan dalam {$days} hari. Berapa hari yang dibutuhkan {$workers} pekerja dengan kecepatan yang sama?", intdiv(4 * $days, $workers)],
            default => ["Ibu membeli {$apples} apel seharga Rp{$each} per buah dan membayar dengan uang Rp50000. Berapa Rupiah kembaliannya?", 50000 - $apples * $each],
        };

        return self::number($text, $answer);
    }

    /** Six kinds of series, each with its own start and step for every round, so the items differ. @return array<string, mixed> */
    private static function series(int $i): array
    {
        $k = intdiv($i, 6);
        $terms = [];

        switch ($i % 6) {
            case 0:
                $terms = array_map(static fn (int $n): int => 2 + 2 * $k + (2 + $k) * $n, range(0, 5));
                break;
            case 1:
                $terms = array_map(static fn (int $n): int => (1 + $k) * 2 ** $n, range(0, 5));
                break;
            case 2:
                $terms = array_map(static fn (int $n): int => (1 + $k) * 3 ** $n, range(0, 5));
                break;
            case 3:
                $value = 10 + $k;

                foreach (range(0, 5) as $n) {
                    $terms[] = $value;
                    $value += $n % 2 === 0 ? 4 + $k % 3 : -(1 + $k % 2);
                }

                break;
            case 4:
                $terms = array_map(static fn (int $n): int => ($n + 1) ** 2 + 2 * $k, range(0, 5));
                break;
            default:
                $terms = [1 + $k % 4, 2 + $k % 5];

                while (count($terms) < 6) {
                    $terms[] = $terms[count($terms) - 1] + $terms[count($terms) - 2];
                }
        }

        $answer = array_pop($terms);

        return self::number('Lanjutkan deret angka berikut: '.implode(', ', $terms).', …', $answer);
    }

    /** Syllogisms, converse statements, letter series and ordering puzzles in turn. @return array<string, mixed> */
    private static function logic(int $i): array
    {
        $k = intdiv($i, 4);

        switch ($i % 4) {
            case 0:
                [$x, $y, $z] = DemoPsychometricBanks::syllogisms()[$k % count(DemoPsychometricBanks::syllogisms())];

                return self::trueFalse("Semua {$x} adalah {$y}. Semua {$y} adalah {$z}. Apakah semua {$x} adalah {$z}?", true);
            case 1:
                [$x, $y] = DemoPsychometricBanks::syllogisms()[$k % count(DemoPsychometricBanks::syllogisms())];

                return self::trueFalse("Semua {$x} adalah {$y}. Apakah semua {$y} adalah {$x}?", false);
            case 2:
                $letters = range('A', 'Z');
                $step = 2 + $k % 2;
                $first = intdiv($k, 2) % 10;
                $shown = array_map(static fn (int $n): string => $letters[$first + $step * $n], range(0, 4));
                $answer = $letters[$first + $step * 5];

                return self::choice('Lanjutkan deret huruf berikut: '.implode(', ', $shown).', …', $answer, [$letters[$first + $step * 5 + 1], $letters[$first + $step * 5 - 1], $letters[$first + $step * 5 + 2]], $i);
            default:
                $names = DemoPsychometricBanks::names();
                [$a, $b, $c] = [$names[$k % 14], $names[($k + 1) % 14], $names[($k + 2) % 14]];
                $tallest = $k % 2 === 0;

                return self::choice(
                    "{$a} lebih tinggi daripada {$b}, dan {$b} lebih tinggi daripada {$c}. Siapakah yang paling ".($tallest ? 'tinggi' : 'pendek').'?',
                    $tallest ? $a : $c,
                    [$b, $tallest ? $c : $a, $names[($k + 3) % 14]],
                    $i,
                );
        }
    }

    /** @return array<string, mixed> */
    private static function checking(int $i): array
    {
        $letters = ['AX', 'KR', 'TM', 'PL', 'GH', 'ZB'];
        $first = $letters[$i % 6].'-'.(4000 + $i * 137).'-'.$letters[($i + 2) % 6];
        $same = $i % 2 === 0;
        $position = 3 + $i % 4;
        $second = $same ? $first : substr_replace($first, (string) (((int) $first[$position] + 1) % 10), $position, 1);

        return self::trueFalse("Apakah kedua kode berikut sama persis?\n{$first}\n{$second}", $same);
    }

    /**
     * @param  list<string>  $wrong  more wrong answers than needed, from which three different ones are taken
     * @return array<string, mixed>
     */
    private static function choice(string $text, string $right, array $wrong, int $i): array
    {
        $picked = [];

        foreach ($wrong as $label) {
            if (mb_strtolower($label) !== mb_strtolower($right) && ! in_array(mb_strtolower($label), array_map('mb_strtolower', $picked), true) && count($picked) < 3) {
                $picked[] = $label;
            }
        }

        $options = array_map(static fn (string $label): array => [$label, false, null], $picked);
        array_splice($options, $i % 4, 0, [[$right, true, null]]);

        return ['type' => QuestionType::SingleChoice, 'text' => $text, 'options' => $options];
    }

    /** @return array<string, mixed> */
    private static function number(string $text, int $answer): array
    {
        return ['type' => QuestionType::Number, 'text' => $text, 'options' => [[(string) $answer, true, null]]];
    }

    /** @return array<string, mixed> */
    private static function trueFalse(string $text, bool $right): array
    {
        return ['type' => QuestionType::TrueFalse, 'text' => $text, 'options' => [['Benar', $right, null], ['Salah', ! $right, null]]];
    }

    /**
     * The scales the sections use, in order, with the number of questions of each.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, int>
     */
    private static function scalesOf(array $sections): array
    {
        $counts = [];

        foreach ($sections as $section) {
            foreach ($section['questions'] as $question) {
                $counts[$question['scale']] = ($counts[$question['scale']] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * The norm table: the raw range of each scale split into five bands of nearly equal width, from very low to very high.
     *
     * @param  array<string, int>  $scales  the question count of each scale
     * @return list<Norm>
     */
    private static function norms(array $scales): array
    {
        $norms = [];

        foreach ($scales as $scale => $count) {
            $meta = DemoPsychometricBanks::scales()[$scale];
            $max = $meta['kind'] === 'attitude' ? $count * 5 : $count;

            foreach (self::BANDS as $index => [$band, $standard, $phrase]) {
                $from = intdiv($index * ($max + 1), 5);
                $to = intdiv(($index + 1) * ($max + 1), 5) - 1;

                $norms[] = new Norm($scale, $from, $to, $standard, $band, "{$meta['label']}: skor Anda tergolong {$band}, {$phrase}. Skala ini menggambarkan {$meta['about']}.");
            }
        }

        return $norms;
    }

    /** @param  array<string, int>  $scales */
    private static function report(array $scales): string
    {
        $lines = [
            "LAPORAN HASIL PEMERIKSAAN PSIKOLOGI\nNomor laporan: {{report_number}}\n",
            "Nama peserta: {{candidate_name}}\nUsia: {{candidate_age}} tahun\nPosisi yang dilamar: {{job_title}}\nInstrumen: {{test_title}}\n",
            'HASIL PER ASPEK',
        ];

        foreach (array_keys($scales) as $scale) {
            $lines[] = '- '.DemoPsychometricBanks::scales()[$scale]['label'].": skor mentah {{scale:{$scale}:raw}}, skor standar {{scale:{$scale}:standard}}, kategori {{scale:{$scale}:band}}";
        }

        $lines[] = "\nREKOMENDASI\n{{recommendation}}\n\nSkor standar memakai skala dengan rata-rata 100. Hasil ini dipakai sebagai salah satu pertimbangan seleksi bersama wawancara dan data lain.";
        $lines[] = "\nDitandatangani pada {{signed_on}} oleh {{psychologist_name}}.";

        return implode("\n", $lines);
    }

    /** @param  array<string, int>  $scales */
    private static function explanation(array $scales): string
    {
        $lines = ["Yth. {{candidate_name}},\n\nBerikut penjelasan hasil {{test_title}} yang Anda kerjakan. Hasil ini menggambarkan kondisi Anda saat tes dan bukan penilaian atas diri Anda secara keseluruhan.\n"];

        foreach (array_keys($scales) as $scale) {
            $lines[] = "- {{scale:{$scale}:explanation}}";
        }

        $lines[] = "\nAnda berhak bertanya tentang hasil ini kepada lembaga yang menerbitkannya. Laporan nomor {{report_number}} ditandatangani oleh {{psychologist_name}} pada {{signed_on}}.";

        return implode("\n", $lines);
    }
}
