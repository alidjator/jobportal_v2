<?php

namespace Src\DemoData\Application;

use Src\Assessment\Domain\QuestionType;
use Src\Company\Domain\MemberPreset;

/**
 * The fictitious skill tests of the verified company: four auto-scored tests (no assessor needed) and the job each one is the default test of. The texts are
 * Indonesian, as the candidates see them. A question is `[type, text, points, options]`; an option is `[label, correct]` (a number or short-text question
 * lists its accepted answers, all correct).
 */
final class DemoAssessmentContent
{
    /**
     * @return array<string, array{title: string, description: string, instructions: string, capabilities: list<string>, sections: list<array{title: string, instructions: string, minutes: int, questions: list<array{QuestionType, string, int, list<array{string, bool}>}>}>}>
     */
    public static function tests(): array
    {
        return [
            'backend' => [
                'title' => 'Tes dasar pemrograman backend',
                'description' => 'Menguji pemahaman dasar API, basis data, keamanan dan logika untuk calon backend developer.',
                'instructions' => 'Kerjakan sendiri tanpa bantuan orang lain. Setiap bagian punya batas waktu sendiri dan semua soal dinilai otomatis.',
                'capabilities' => ['Dasar HTTP dan API', 'Basis data', 'Keamanan aplikasi', 'Logika dan hitungan'],
                'sections' => [
                    ['title' => 'Konsep dasar', 'instructions' => 'Pilih jawaban yang paling tepat.', 'minutes' => 10, 'questions' => [
                        [QuestionType::SingleChoice, 'Kode status HTTP mana yang paling tepat saat sebuah sumber daya berhasil dibuat lewat permintaan POST?', 1, [['200', false], ['201', true], ['204', false], ['301', false]]],
                        [QuestionType::SingleChoice, 'Apa fungsi utama indeks pada tabel basis data?', 1, [['Mempercepat pencarian dan pengurutan data', true], ['Mengenkripsi isi tabel', false], ['Mencegah data dihapus', false], ['Mengurangi jumlah kolom', false]]],
                        [QuestionType::MultipleResponse, 'Mana saja praktik yang membuat sebuah API lebih aman? Pilih semua yang benar.', 2, [['Memvalidasi semua masukan', true], ['Menyimpan kata sandi sebagai teks biasa', false], ['Membatasi jumlah permintaan per pengguna', true], ['Memakai prepared statement untuk query', true]]],
                        [QuestionType::TrueFalse, 'Masalah query N+1 dapat dikurangi dengan eager loading.', 1, [['Benar', true], ['Salah', false]]],
                        [QuestionType::TrueFalse, 'Metode GET boleh dipakai untuk mengubah data di server.', 1, [['Benar', false], ['Salah', true]]],
                    ]],
                    ['title' => 'Logika dan hitungan', 'instructions' => 'Tulis jawaban berupa angka atau kata singkat.', 'minutes' => 10, 'questions' => [
                        [QuestionType::Number, 'Sebuah antrean memproses 12 lamaran per menit. Berapa lamaran yang diproses dalam 2,5 jam?', 2, [['1800', true]]],
                        [QuestionType::Number, 'Berapa hasil dari 7 + 3 × 4?', 1, [['19', true]]],
                        [QuestionType::ShortText, 'Sebutkan perintah SQL untuk menggabungkan baris dua tabel berdasarkan kunci yang sama (satu kata).', 2, [['JOIN', true], ['INNER JOIN', true]]],
                        [QuestionType::SingleChoice, 'Pencarian biner pada larik yang sudah terurut dengan n elemen memiliki kompleksitas waktu…', 2, [['O(n)', false], ['O(log n)', true], ['O(n²)', false], ['O(1)', false]]],
                    ]],
                ],
            ],
            'data' => [
                'title' => 'Tes SQL dan analisis data',
                'description' => 'Menguji SQL dasar dan cara berpikir analitis untuk calon analis data.',
                'instructions' => 'Kerjakan sendiri. Semua soal dinilai otomatis dan tidak ada nilai negatif untuk jawaban yang salah.',
                'capabilities' => ['SQL', 'Statistik dasar', 'Visualisasi data'],
                'sections' => [
                    ['title' => 'SQL dasar', 'instructions' => 'Pilih jawaban yang paling tepat.', 'minutes' => 15, 'questions' => [
                        [QuestionType::SingleChoice, 'Klausa SQL mana yang dipakai untuk menyaring hasil setelah pengelompokan (GROUP BY)?', 1, [['WHERE', false], ['HAVING', true], ['LIMIT', false], ['DISTINCT', false]]],
                        [QuestionType::SingleChoice, 'Bagaimana cara mengurutkan hasil dari nilai terbesar ke terkecil?', 1, [['ORDER BY nilai ASC', false], ['ORDER BY nilai DESC', true], ['GROUP BY nilai', false], ['SORT nilai', false]]],
                        [QuestionType::MultipleResponse, 'Mana saja yang termasuk fungsi agregat? Pilih semua yang benar.', 2, [['SUM', true], ['COUNT', true], ['UPPER', false], ['MAX', true]]],
                        [QuestionType::TrueFalse, 'LEFT JOIN mempertahankan semua baris dari tabel di sisi kiri.', 1, [['Benar', true], ['Salah', false]]],
                        [QuestionType::ShortText, 'Kata kunci SQL apa yang menghilangkan baris duplikat dari hasil SELECT?', 2, [['DISTINCT', true]]],
                    ]],
                    ['title' => 'Analisis data', 'instructions' => 'Tulis angka atau pilih jawaban yang paling tepat.', 'minutes' => 10, 'questions' => [
                        [QuestionType::Number, 'Dari 250 pelamar, 40% lolos tahap penyaringan. Berapa pelamar yang lolos?', 2, [['100', true]]],
                        [QuestionType::Number, 'Berapa rata-rata dari 12, 18, 15 dan 15?', 1, [['15', true]]],
                        [QuestionType::SingleChoice, 'Grafik apa yang paling tepat untuk menampilkan tren jumlah pelamar per bulan selama satu tahun?', 1, [['Grafik garis', true], ['Grafik lingkaran', false], ['Tabel tanpa grafik', false], ['Grafik sebar tiga dimensi', false]]],
                        [QuestionType::TrueFalse, 'Dua variabel yang berkorelasi pasti berarti yang satu menyebabkan yang lain.', 1, [['Benar', false], ['Salah', true]]],
                    ]],
                ],
            ],
            'support' => [
                'title' => 'Tes situasi layanan pelanggan',
                'description' => 'Menguji sikap dan penilaian calon petugas layanan pelanggan pada situasi sehari-hari.',
                'instructions' => 'Pilih tindakan yang menurut Anda paling tepat. Soal dinilai otomatis.',
                'capabilities' => ['Empati', 'Komunikasi tertulis', 'Penentuan prioritas'],
                'sections' => [
                    ['title' => 'Situasi layanan pelanggan', 'instructions' => 'Bayangkan Anda bertugas di meja layanan kami.', 'minutes' => 10, 'questions' => [
                        [QuestionType::SingleChoice, 'Seorang pelanggan marah karena pesanannya terlambat tiga hari. Apa langkah pertama Anda?', 2, [['Mendengarkan, mengakui masalahnya dan meminta maaf', true], ['Menjelaskan bahwa keterlambatan itu bukan kesalahan kita', false], ['Meneruskan pelanggan ke atasan tanpa penjelasan', false], ['Menawarkan diskon sebelum mengetahui masalahnya', false]]],
                        [QuestionType::SingleChoice, 'Pelanggan bertanya hal yang belum Anda ketahui jawabannya. Apa yang Anda lakukan?', 2, [['Menebak jawaban yang paling mungkin', false], ['Jujur, mencari jawaban yang benar lalu menghubungi pelanggan kembali', true], ['Mengatakan bahwa itu bukan urusan kita', false], ['Menutup percakapan', false]]],
                        [QuestionType::MultipleResponse, 'Mana saja unsur balasan email yang baik kepada pelanggan? Pilih semua yang benar.', 2, [['Salam dan sapaan yang sopan', true], ['Rangkuman masalah pelanggan', true], ['Menyalahkan pelanggan atas kesalahannya', false], ['Langkah berikutnya dan perkiraan waktu', true]]],
                        [QuestionType::TrueFalse, 'Data pribadi pelanggan boleh dibagikan kepada rekan yang tidak menangani kasusnya.', 1, [['Benar', false], ['Salah', true]]],
                        [QuestionType::Number, 'Target kami: 90% dari 200 tiket dibalas dalam satu jam. Berapa tiket paling sedikit yang harus dibalas tepat waktu?', 1, [['180', true]]],
                        [QuestionType::SingleChoice, 'Dua tiket masuk bersamaan: satu pelanggan tidak bisa masuk ke akunnya sama sekali, satu lagi menanyakan fitur baru. Mana yang Anda kerjakan lebih dulu?', 2, [['Pelanggan yang tidak bisa masuk ke akunnya', true], ['Pertanyaan tentang fitur baru', false], ['Yang masuk lebih dulu dalam daftar', false], ['Yang bahasanya lebih sopan', false]]],
                    ]],
                ],
            ],
            'uiux' => [
                'title' => 'Tes pengetahuan UI/UX',
                'description' => 'Menguji dasar riset pengguna, aksesibilitas dan prinsip desain antarmuka.',
                'instructions' => 'Kerjakan sendiri. Soal pilihan dinilai otomatis; jawaban uraian pada studi kasus dinilai oleh penilai dari tim perekrutan.',
                'capabilities' => ['Riset pengguna', 'Aksesibilitas', 'Prinsip desain antarmuka', 'Studi kasus'],
                'sections' => [
                    ['title' => 'Dasar UI/UX', 'instructions' => 'Pilih jawaban yang paling tepat.', 'minutes' => 12, 'questions' => [
                        [QuestionType::SingleChoice, 'Apa tujuan utama wireframe?', 1, [['Menggambarkan struktur dan alur halaman tanpa detail visual', true], ['Menentukan warna akhir produk', false], ['Menggantikan kode program', false], ['Menyimpan data pengguna', false]]],
                        [QuestionType::SingleChoice, 'Berapa rasio kontras minimum WCAG AA untuk teks berukuran normal?', 2, [['2:1', false], ['3:1', false], ['4,5:1', true], ['10:1', false]]],
                        [QuestionType::MultipleResponse, 'Mana saja yang termasuk metode riset pengguna? Pilih semua yang benar.', 2, [['Wawancara pengguna', true], ['Uji kegunaan', true], ['Menebak kebutuhan pengguna', false], ['Survei', true]]],
                        [QuestionType::TrueFalse, 'Target sentuh yang terlalu kecil dapat menurunkan kegunaan aplikasi di ponsel.', 1, [['Benar', true], ['Salah', false]]],
                        [QuestionType::Number, 'Berapa pengguna yang sering dianggap cukup dalam satu putaran uji kegunaan kecil untuk menemukan sebagian besar masalah utama?', 2, [['5', true]]],
                        [QuestionType::SingleChoice, 'Menurut hukum Fitts, waktu menjangkau sebuah target bergantung pada…', 2, [['Jarak ke target dan ukuran target', true], ['Warna target', false], ['Jumlah halaman aplikasi', false], ['Bahasa antarmuka', false]]],
                    ]],
                    ['title' => 'Studi kasus', 'instructions' => 'Jawab dengan uraian singkat dan konkret. Jawaban dinilai oleh penilai dari tim perekrutan.', 'minutes' => 20, 'questions' => [
                        [QuestionType::LongText, 'Pilih satu bagian aplikasi lamaran kerja yang menurut Anda paling membingungkan pengguna. Jelaskan masalahnya dan bagaimana Anda akan memperbaikinya.', 10, []],
                        [QuestionType::LongText, 'Uraikan langkah Anda merancang ulang alur pendaftaran akun agar lebih singkat tanpa mengorbankan keamanan.', 10, []],
                    ]],
                ],
            ],
        ];
    }

    /**
     * The default test of each job at its assessment stage: the test key, the days to take it and the percentage from which an application moves on to
     * the interview stage by itself (null: it stays for the team), and the presets of the members who score its long-text answers.
     *
     * @return array<string, array{0: string, 1: int, 2: ?int, 3?: list<string>}>
     */
    public static function jobDefaults(): array
    {
        return [
            'backend' => ['backend', 5, 70],
            'mobile' => ['backend', 5, null],
            'data' => ['data', 5, 70],
            'support' => ['support', 3, null],
            'uiux' => ['uiux', 5, null, [MemberPreset::Recruiter->value]],
        ];
    }
}
