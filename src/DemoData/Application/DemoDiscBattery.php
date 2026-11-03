<?php

namespace Src\DemoData\Application;

use Src\Assessment\Domain\MostLeast;
use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionFormat;

/**
 * The batteries of the DISC family of the demo provider (`docs/design/phase-8-disc-profile.md` D1), in two sizes:
 *
 * - `disc`: six groups of four **obviously fictitious** statements and a fictitious norm table, to try the whole path.
 * - `disc_full`: the classic 24 groups of four statements, one per dimension. The statements are **original**, written for the demo (no item of a published instrument), and the norms are **provisional and theoretical**: the
 *   T scores follow the spread of the counts when every statement of a group is equally likely to be picked, not a norm group. The battery is not standardised; a partner writes and validates its own.
 *
 * The platform ships no instrument content of its own: both batteries are demo data.
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

    /** The 24 groups of the full battery, each in the order of `DIMENSIONS` (d, i, s, c). Original statements written for the demo. */
    private const array STATEMENTS = [
        ['Saya senang mengambil keputusan dengan cepat saat tim ragu.', 'Saya mudah memulai obrolan dengan orang yang baru dikenal.', 'Saya lebih nyaman bekerja dengan ritme yang tetap.', 'Saya memeriksa ulang pekerjaan sebelum menyerahkannya.'],
        ['Saya terdorong oleh target yang menantang.', 'Saya suka menyemangati rekan ketika suasana sedang lesu.', 'Saya sabar mendengarkan keluhan orang lain sampai selesai.', 'Saya mengikuti prosedur yang berlaku meskipun ada jalan pintas.'],
        ['Saya berani menyampaikan pendapat yang berbeda di depan atasan.', 'Saya senang tampil dan berbicara di depan banyak orang.', 'Saya menjaga hubungan baik dengan rekan dalam jangka panjang.', 'Saya suka bekerja dengan data dan angka yang pasti.'],
        ['Saya cepat mengambil alih ketika sebuah pekerjaan tidak berjalan.', 'Saya mudah meyakinkan orang lain untuk mendukung ide saya.', 'Saya menyelesaikan tugas yang sudah saya mulai, satu per satu.', 'Saya menyiapkan rencana yang rinci sebelum mulai bekerja.'],
        ['Saya menikmati persaingan untuk menjadi yang terbaik.', 'Saya senang bekerja dalam kelompok yang ramai dan akrab.', 'Saya bersedia membantu rekan walaupun itu bukan tugas saya.', 'Saya memperhatikan hal kecil yang sering terlewat oleh orang lain.'],
        ['Saya lebih suka diberi hasil yang harus dicapai daripada cara mengerjakannya.', 'Saya sering mencairkan suasana dengan humor.', 'Saya tidak mudah terpancing emosi saat berada di bawah tekanan.', 'Saya ingin memahami alasan sebuah aturan sebelum menjalankannya.'],
        ['Saya tidak segan menegur rekan yang lalai.', 'Saya optimis bahwa sebagian besar masalah ada jalan keluarnya.', 'Saya lebih suka perubahan yang dilakukan secara bertahap.', 'Saya menimbang semua pilihan sebelum memutuskan.'],
        ['Saya suka mencoba cara baru meskipun ada risikonya.', 'Saya senang memperkenalkan orang yang satu dengan yang lain.', 'Saya dikenal sebagai pendengar yang baik.', 'Saya menata dokumen dan berkas dengan teratur.'],
        ['Saya tegas memegang keputusan yang sudah saya ambil.', 'Saya mudah mengungkapkan perasaan dan antusiasme saya.', 'Saya setia pada tim dan organisasi tempat saya bekerja.', 'Saya menuntut ketepatan dari hasil kerja saya sendiri.'],
        ['Saya bekerja paling baik ketika diberi wewenang penuh.', 'Saya senang mendapat pengakuan atas apa yang saya kerjakan.', 'Saya merasa tenang bila tahu apa yang diharapkan dari saya.', 'Saya menghindari kesalahan dengan mengikuti standar yang jelas.'],
        ['Saya langsung ke pokok persoalan saat berbicara.', 'Saya pandai bercerita sehingga orang tertarik mendengarkan.', 'Saya mendahulukan kerukunan daripada memenangkan perdebatan.', 'Saya mencatat hal penting supaya tidak ada yang terlupa.'],
        ['Saya mendorong tim untuk bergerak lebih cepat.', 'Saya membawa semangat baru ketika bergabung dengan tim.', 'Saya konsisten mengerjakan tugas rutin dengan baik.', 'Saya meneliti informasi sampai benar-benar yakin.'],
        ['Saya tidak mudah menyerah saat menghadapi hambatan.', 'Saya mudah akrab dengan berbagai macam orang.', 'Saya menghargai suasana kerja yang damai dan stabil.', 'Saya bekerja secara sistematis, mengikuti urutan langkah.'],
        ['Saya suka memegang kendali atas hasil akhir pekerjaan.', 'Saya senang bertukar ide secara spontan dengan rekan.', 'Saya meluangkan waktu untuk membantu orang baru beradaptasi.', 'Saya memastikan setiap angka dalam laporan sudah benar.'],
        ['Saya menetapkan sasaran yang tinggi untuk diri sendiri.', 'Saya mampu membuat orang lain merasa diterima.', 'Saya tetap tenang walaupun pekerjaan menumpuk.', 'Saya lebih percaya pada bukti daripada perasaan.'],
        ['Saya berani bertanggung jawab atas keputusan yang sulit.', 'Saya senang menjadi pusat perhatian dalam acara tim.', 'Saya memakai cara kerja yang sudah terbukti berhasil.', 'Saya mengajukan banyak pertanyaan untuk memahami tugas dengan tepat.'],
        ['Saya cepat bertindak begitu melihat peluang.', 'Saya pandai mengajak orang untuk mencoba hal baru.', 'Saya bisa diandalkan untuk hadir dan menepati janji.', 'Saya membaca ketentuan dengan teliti sebelum menyetujui sesuatu.'],
        ['Saya menyukai tugas yang penuh tantangan dan tekanan.', 'Saya menjaga suasana tim tetap ceria.', 'Saya lebih suka bekerja sama daripada bersaing.', 'Saya menyusun pekerjaan menurut prioritas yang jelas dan terukur.'],
        ['Saya berbicara terus terang meskipun pendapat saya kurang populer.', 'Saya mudah bersemangat terhadap ide baru.', 'Saya menunggu sampai keadaan jelas sebelum berganti arah.', 'Saya menjaga mutu pekerjaan sesuai standar yang ditetapkan.'],
        ['Saya ingin melihat hasil yang nyata dalam waktu singkat.', 'Saya senang bekerja dengan banyak orang dari berbagai bagian.', 'Saya mudah memaafkan dan tidak menyimpan dendam.', 'Saya berhati-hati agar tidak menarik kesimpulan terlalu cepat.'],
        ['Saya mengambil inisiatif tanpa harus menunggu perintah.', 'Saya sering diminta memandu acara atau pertemuan.', 'Saya rendah hati dan tidak suka menonjolkan diri.', 'Saya rapi dan terorganisasi dalam mengatur waktu.'],
        ['Saya terbiasa memimpin ketika tidak ada yang mengambil peran itu.', 'Saya mudah membangun jejaring dengan orang baru.', 'Saya sabar menghadapi pekerjaan yang membutuhkan waktu lama.', 'Saya suka menganalisis masalah sampai ke akarnya.'],
        ['Saya berusaha keluar sebagai pemenang dalam setiap negosiasi.', 'Saya bisa membuat orang lain ikut bersemangat.', 'Saya menjadi penengah ketika rekan berselisih.', 'Saya menguji sebuah cara terlebih dahulu sebelum dipakai secara luas.'],
        ['Saya menyukai kebebasan untuk menentukan cara kerja sendiri.', 'Saya terbuka menceritakan pengalaman saya kepada orang lain.', 'Saya merasa puas ketika bisa melayani orang lain dengan baik.', 'Saya menepati tenggat berkat perencanaan yang cermat.'],
    ];

    /** The T score is kept between these marks; the band is low below the first and high from the second (`MostLeast` reads the high mark from the rows). */
    private const array T_LIMITS = [20, 80];

    private const array T_BANDS = [[40, 'Rendah'], [60, 'Sedang'], [PHP_INT_MAX, 'Tinggi']];

    /** What each graph of the full battery counts, and where each band lies. */
    private const array GRAPH_TEXT = [
        'most' => 'seberapa sering pernyataan dimensi ini Anda pilih sebagai paling sesuai',
        'least' => 'seberapa sering pernyataan dimensi ini Anda pilih sebagai paling tidak sesuai',
        'change' => 'selisih antara pilihan paling dan paling tidak pada dimensi ini',
    ];

    private const array BAND_TEXT = [
        'Rendah' => 'lebih rendah daripada rata-rata teoretis',
        'Sedang' => 'sekitar rata-rata teoretis',
        'Tinggi' => 'lebih tinggi daripada rata-rata teoretis',
    ];

    /**
     * @param  bool  $full  the 24 original groups with theoretical norms instead of the six fictitious ones
     * @return array{title: string, description: string, instructions: string, capabilities: list<string>, sections: list<array<string, mixed>>, norms: list<Norm>, report: string, explanation: string, key_scales: list<string>, recommended_min: float, not_yet_below: float}
     */
    public static function battery(bool $full = false): array
    {
        return [
            'title' => $full ? 'Profil Perilaku DISC (24 kelompok, belum terstandardisasi)' : 'Profil Perilaku DISC (contoh fiktif)',
            'description' => $full
                ? 'Baterai keluarga DISC dengan 24 kelompok pernyataan orisinal yang ditulis untuk demo. Normanya sementara dan teoretis (dari sebaran pilihan acak), bukan hasil standardisasi pada kelompok pembanding.'
                : 'Contoh baterai keluarga DISC dengan pernyataan dan norma fiktif, untuk mencoba seluruh alurnya. Penyedia menulis baterainya sendiri.',
            'instructions' => $full
                ? 'Di setiap kelompok ada empat pernyataan tentang cara Anda bekerja. Tandai satu yang paling sesuai dengan Anda dan satu yang paling tidak sesuai. Jawablah sesuai keadaan Anda sehari-hari, bukan keadaan yang Anda harapkan. Tidak ada jawaban benar atau salah.'
                : 'Di setiap kelompok ada empat pernyataan. Tandai satu yang paling sesuai dengan Anda dan satu yang paling tidak sesuai. Tidak ada jawaban benar atau salah. Semua pernyataan di sini fiktif.',
            'capabilities' => ['Gaya perilaku kerja'],
            'sections' => [[
                'title' => 'Kelompok pernyataan', 'instructions' => 'Tandai satu pernyataan paling sesuai dan satu paling tidak sesuai di setiap kelompok.',
                'format' => SectionFormat::Questions, 'minutes' => $full ? 20 : 15, 'settings' => [], 'questions' => self::questions($full),
            ]],
            'norms' => $full ? self::theoreticalNorms() : self::norms(),
            'report' => self::report($full),
            'explanation' => self::explanation($full),
            'key_scales' => ['d_most', 'i_most'],
            'recommended_min' => 55.0,
            'not_yet_below' => 45.0,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function questions(bool $full): array
    {
        $dimensions = array_keys(self::DIMENSIONS);
        $groups = $full ? count(self::STATEMENTS) : self::GROUPS;
        $questions = [];

        for ($group = 1; $group <= $groups; $group++) {
            $options = [];

            // The dimensions rotate from group to group, so the same position is not always the same dimension.
            foreach (array_keys($dimensions) as $index) {
                $at = ($index + $group) % count($dimensions);
                $dimension = $dimensions[$at];
                $label = $full ? self::STATEMENTS[$group - 1][$at] : "Contoh pernyataan fiktif {$group}{$dimension} (bukan butir instrumen sungguhan)";
                $options[] = [$label, false, null, $dimension];
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

    /**
     * The provisional norm table of the full battery. With one statement per dimension in each of N groups and every pick equally likely, a dimension is picked as most with a chance of 1/4 per group: the count of
     * `_most` (and of `_least`) has a mean of N/4 and a variance of 3N/16. The change of a group is +1 or -1 with a chance of 1/4 each and 0 otherwise: mean 0, variance N/2. Each raw score gets the T score
     * 50 + 10z, rounded and kept between `T_LIMITS`; neighbouring raw scores with the same T score share a row. 24 groups give under 400 rows.
     *
     * @return list<Norm>
     */
    private static function theoreticalNorms(): array
    {
        $groups = count(self::STATEMENTS);
        $norms = [];

        foreach (self::DIMENSIONS as $dimension => [$label, $about]) {
            foreach (self::GRAPHS as $graph => [$graphName]) {
                [$from, $mean, $deviation] = $graph === 'change' ? [-$groups, 0.0, sqrt($groups / 2)] : [0, $groups / 4, sqrt(3 * $groups / 16)];
                $rows = [];

                for ($raw = $from; $raw <= $groups; $raw++) {
                    $standard = (int) max(self::T_LIMITS[0], min(self::T_LIMITS[1], round(50 + 10 * ($raw - $mean) / $deviation)));
                    $last = array_key_last($rows);

                    if ($last !== null && $rows[$last][2] === $standard) {
                        $rows[$last][1] = $raw;
                    } else {
                        $rows[] = [$raw, $raw, $standard];
                    }
                }

                foreach ($rows as [$rawMin, $rawMax, $standard]) {
                    $band = self::band($standard);
                    $norms[] = new Norm(
                        MostLeast::key($dimension, $graph), $rawMin, $rawMax, (float) $standard, $band,
                        "{$label} ({$graphName}): skor Anda tergolong {$band}. Dimensi ini menggambarkan {$about}. Grafik {$graphName} menunjukkan ".self::GRAPH_TEXT[$graph].', dan skor Anda '.self::BAND_TEXT[$band].'. Norma ini masih sementara dan belum terstandardisasi.',
                    );
                }
            }
        }

        return $norms;
    }

    private static function band(int $standard): string
    {
        foreach (self::T_BANDS as [$below, $band]) {
            if ($standard < $below) {
                return $band;
            }
        }

        return 'Tinggi';
    }

    private static function report(bool $full): string
    {
        $lines = [
            ($full ? 'LAPORAN PROFIL PERILAKU' : 'LAPORAN PROFIL PERILAKU (CONTOH FIKTIF)')."\nNomor laporan: {{report_number}}\n",
            "Nama peserta: {{candidate_name}}\nPosisi yang dilamar: {{job_title}}\nInstrumen: {{test_title}}\n",
            "PROFIL\nDimensi tinggi pada grafik Paling: {{profile:most}}\nDimensi tinggi pada grafik Paling tidak: {{profile:least}}\nDimensi tinggi pada grafik Perubahan: {{profile:change}}\n",
            'HASIL PER DIMENSI',
        ];

        foreach (self::DIMENSIONS as $dimension => [$label]) {
            $lines[] = "- {$label}: Paling {{scale:{$dimension}_most:raw}} (skor standar {{scale:{$dimension}_most:standard}}), Paling tidak {{scale:{$dimension}_least:raw}} (skor standar {{scale:{$dimension}_least:standard}}), Perubahan {{scale:{$dimension}_change:raw}} (skor standar {{scale:{$dimension}_change:standard}})";
        }

        $lines[] = "\nREKOMENDASI\n{{recommendation}}\n\n".($full
            ? 'Instrumen ini belum terstandardisasi: butirnya ditulis untuk demo dan normanya teoretis. Laporan ini hanya untuk mencoba alur dan tidak boleh menjadi satu-satunya dasar keputusan.'
            : 'Seluruh isi laporan ini adalah contoh fiktif dan tidak boleh dipakai untuk keputusan sungguhan.');
        $lines[] = "\nDitandatangani pada {{signed_on}} oleh {{psychologist_name}}.";

        return implode("\n", $lines);
    }

    private static function explanation(bool $full): string
    {
        $lines = ["Yth. {{candidate_name}},\n\nBerikut penjelasan hasil {{test_title}} yang Anda kerjakan. ".($full ? 'Instrumen ini belum terstandardisasi, jadi bacalah hasilnya sebagai gambaran awal.' : 'Isinya contoh fiktif untuk mencoba alurnya.')."\n"];

        foreach (array_keys(self::DIMENSIONS) as $dimension) {
            foreach (array_keys(self::GRAPHS) as $graph) {
                $lines[] = '- {{scale:'.MostLeast::key($dimension, $graph).':explanation}}';
            }
        }

        $lines[] = "\nLaporan nomor {{report_number}} ditandatangani oleh {{psychologist_name}} pada {{signed_on}}.";

        return implode("\n", $lines);
    }
}
