<?php

namespace Src\DemoData\Application;

use Src\Assessment\Domain\QuestionType;

/**
 * The fictitious tests of the system library, written by the platform admin for every company to send: general, auto-scored skill tests, in the shape of
 * `DemoAssessmentContent::tests()`. The texts are Indonesian (the English test is in English, as its candidates read it).
 */
final class DemoLibraryContent
{
    /**
     * @return array<string, array{title: string, description: string, instructions: string, capabilities: list<string>, sections: list<array{title: string, instructions: string, minutes: int, questions: list<array{QuestionType, string, int, list<array{string, bool}>}>}>}>
     */
    public static function tests(): array
    {
        return [
            'numeric' => [
                'title' => 'Tes penalaran numerik dasar',
                'description' => 'Menguji hitungan sehari-hari: persentase, rasio, deret, kecepatan dan rata-rata.',
                'instructions' => 'Kalkulator tidak diperlukan. Tulis angka tanpa satuan dan tanpa tanda pemisah ribuan.',
                'capabilities' => ['Persentase dan rasio', 'Deret angka', 'Penalaran logis'],
                'sections' => [
                    ['title' => 'Hitungan dasar', 'instructions' => 'Kerjakan secepat dan seteliti mungkin.', 'minutes' => 15, 'questions' => [
                        [QuestionType::Number, 'Berapa 15% dari 840?', 1, [['126', true]]],
                        [QuestionType::Number, 'Lanjutkan deret berikut: 3, 6, 12, 24, …', 1, [['48', true]]],
                        [QuestionType::SingleChoice, 'Dua bagian dibagi dengan perbandingan 2 : 3 dari total 50. Berapa bagian yang lebih kecil?', 1, [['15', false], ['20', true], ['25', false], ['30', false]]],
                        [QuestionType::Number, 'Sebuah mobil melaju 60 km per jam. Berapa menit yang dibutuhkan untuk menempuh 150 km?', 2, [['150', true]]],
                        [QuestionType::Number, 'Harga barang Rp200.000 mendapat diskon 25%, lalu ditambah PPN 10% dari harga setelah diskon. Berapa Rupiah yang dibayar?', 2, [['165000', true]]],
                        [QuestionType::SingleChoice, 'Rata-rata empat nilai adalah 80. Tiga nilai pertama 70, 80 dan 90. Berapa nilai keempat?', 2, [['70', false], ['80', true], ['90', false], ['100', false]]],
                        [QuestionType::TrueFalse, 'Jika A lebih besar dari B dan B lebih besar dari C, maka A lebih besar dari C.', 1, [['Benar', true], ['Salah', false]]],
                        [QuestionType::Number, 'Tiga pekerja menyelesaikan satu pekerjaan dalam 12 hari. Berapa hari yang dibutuhkan enam pekerja dengan kecepatan sama?', 2, [['6', true]]],
                    ]],
                ],
            ],
            'verbal' => [
                'title' => 'Tes pemahaman bacaan',
                'description' => 'Menguji pemahaman isi bacaan, kosakata dan penarikan kesimpulan.',
                'instructions' => 'Baca bacaan pada bagian soal dengan teliti, lalu jawab soal berdasarkan isinya saja.',
                'capabilities' => ['Pemahaman bacaan', 'Kosakata', 'Penarikan kesimpulan'],
                'sections' => [
                    ['title' => 'Bacaan dan soal', 'instructions' => 'Bacaan: Perusahaan memutuskan memindahkan rapat mingguan dari Senin pagi ke Rabu siang karena banyak anggota tim menerima tugas penting pada awal minggu. Setelah dua bulan, survei menunjukkan kehadiran naik dari 70% menjadi 95%, tetapi beberapa anggota mengeluh bahwa jadwal Rabu mengganggu waktu makan siang mereka.', 'minutes' => 10, 'questions' => [
                        [QuestionType::SingleChoice, 'Apa alasan utama rapat dipindahkan?', 2, [['Banyak anggota tim menerima tugas penting pada awal minggu', true], ['Kantor ditutup pada hari Senin', false], ['Anggota tim ingin rapat lebih singkat', false], ['Biaya rapat terlalu mahal', false]]],
                        [QuestionType::Number, 'Kehadiran rapat naik berapa poin persentase setelah dipindahkan?', 2, [['25', true]]],
                        [QuestionType::TrueFalse, 'Semua anggota tim puas dengan jadwal yang baru.', 1, [['Benar', false], ['Salah', true]]],
                        [QuestionType::SingleChoice, 'Apa keluhan beberapa anggota tim?', 1, [['Jadwal baru mengganggu waktu makan siang', true], ['Rapat terlalu sering', false], ['Rapat terlalu pagi', false], ['Tempat rapat terlalu jauh', false]]],
                        [QuestionType::SingleChoice, 'Kata yang paling dekat artinya dengan "mengeluh" adalah…', 1, [['Mengadu', true], ['Memuji', false], ['Menolak', false], ['Menunda', false]]],
                        [QuestionType::SingleChoice, 'Lawan kata "naik" adalah…', 1, [['Turun', true], ['Tinggi', false], ['Maju', false], ['Tetap', false]]],
                    ]],
                ],
            ],
            'english' => [
                'title' => 'English for business communication',
                'description' => 'Checks grammar, vocabulary and the phrases used in everyday business email.',
                'instructions' => 'Choose the best answer. Do not use a dictionary or a translation tool.',
                'capabilities' => ['Business grammar', 'Vocabulary', 'Email etiquette'],
                'sections' => [
                    ['title' => 'Grammar and vocabulary', 'instructions' => 'Choose the best answer.', 'minutes' => 12, 'questions' => [
                        [QuestionType::SingleChoice, 'Please ___ the attached report before Friday.', 1, [['review', true], ['reviewing', false], ['reviewed', false], ['to reviewing', false]]],
                        [QuestionType::SingleChoice, 'We look forward to ___ from you.', 1, [['hear', false], ['hearing', true], ['heard', false], ['be hearing', false]]],
                        [QuestionType::SingleChoice, 'The meeting has been ___ until next week.', 1, [['postponed', true], ['cancelling', false], ['postpone', false], ['postponing', false]]],
                        [QuestionType::SingleChoice, 'Which closing is the most suitable for a formal email?', 1, [['See ya', false], ['Best regards', true], ['Cheers mate', false], ['Later', false]]],
                        [QuestionType::TrueFalse, '"I am writing to inquire about the vacancy" suits a formal job inquiry.', 1, [['True', true], ['False', false]]],
                        [QuestionType::ShortText, 'Write the past tense of the verb "to send" (one word).', 2, [['sent', true]]],
                        [QuestionType::MultipleResponse, 'Which sentences are correct? Choose all that apply.', 2, [['She doesn\'t work here.', true], ['He have a meeting.', false], ['They are attending the training.', true], ['We was late.', false]]],
                    ]],
                ],
            ],
            'spreadsheet' => [
                'title' => 'Tes dasar spreadsheet',
                'description' => 'Menguji rumus dan fungsi dasar di Microsoft Excel atau Google Sheets.',
                'instructions' => 'Jawab tanpa membuka aplikasi spreadsheet. Tulis nama fungsi dengan huruf besar.',
                'capabilities' => ['Rumus dasar', 'Fungsi pencarian', 'Ringkasan data'],
                'sections' => [
                    ['title' => 'Rumus dan fungsi', 'instructions' => 'Pilih jawaban yang paling tepat.', 'minutes' => 10, 'questions' => [
                        [QuestionType::SingleChoice, 'Rumus mana yang menjumlahkan sel A1 sampai A10?', 1, [['=SUM(A1:A10)', true], ['=ADD(A1,A10)', false], ['=TOTAL(A1-A10)', false], ['=A1+A10', false]]],
                        [QuestionType::SingleChoice, 'Bagaimana cara mengunci referensi sel A1 agar tidak bergeser saat rumus disalin?', 1, [['$A$1', true], ['A1', false], ['#A#1', false], ['A1!', false]]],
                        [QuestionType::ShortText, 'Tulis nama fungsi untuk menghitung banyaknya sel yang berisi angka (satu kata).', 2, [['COUNT', true]]],
                        [QuestionType::SingleChoice, 'Jika B2 berisi 65, apa hasil =IF(B2>=70,"Lulus","Gagal")?', 1, [['Lulus', false], ['Gagal', true], ['65', false], ['Galat', false]]],
                        [QuestionType::MultipleResponse, 'Mana saja yang termasuk fungsi pencarian? Pilih semua yang benar.', 2, [['VLOOKUP', true], ['XLOOKUP', true], ['CONCATENATE', false], ['MATCH', true]]],
                        [QuestionType::Number, 'Berapa hasil =AVERAGE(10,20,30,40)?', 1, [['25', true]]],
                        [QuestionType::TrueFalse, 'Pivot table dapat meringkas data tanpa mengubah data sumbernya.', 1, [['Benar', true], ['Salah', false]]],
                    ]],
                ],
            ],
            'digital' => [
                'title' => 'Tes literasi digital dan keamanan informasi',
                'description' => 'Menguji kewaspadaan terhadap penipuan daring, kata sandi dan informasi yang menyesatkan.',
                'instructions' => 'Pilih jawaban yang menurut Anda paling aman dan bertanggung jawab.',
                'capabilities' => ['Keamanan akun', 'Kewaspadaan terhadap penipuan', 'Literasi informasi'],
                'sections' => [
                    ['title' => 'Keamanan dan literasi digital', 'instructions' => 'Pilih jawaban yang paling tepat.', 'minutes' => 10, 'questions' => [
                        [QuestionType::SingleChoice, 'Anda menerima email yang mengaku dari bank dan meminta Anda menekan tautan untuk mengaktifkan akun yang diblokir. Apa yang Anda lakukan?', 2, [['Tidak menekan tautan, melaporkannya, dan memeriksa lewat kanal resmi bank', true], ['Menekan tautan dan mengisi data dengan cepat', false], ['Meneruskan email kepada semua rekan', false], ['Membalas email dengan nomor kartu Anda', false]]],
                        [QuestionType::MultipleResponse, 'Mana praktik kata sandi yang baik? Pilih semua yang benar.', 2, [['Memakai kata sandi berbeda untuk setiap akun', true], ['Memakai tanggal lahir sebagai kata sandi', false], ['Memakai pengelola kata sandi', true], ['Membagikan kata sandi kepada rekan kerja', false]]],
                        [QuestionType::TrueFalse, 'Autentikasi dua faktor tetap melindungi akun meskipun kata sandi bocor.', 1, [['Benar', true], ['Salah', false]]],
                        [QuestionType::SingleChoice, 'Bagaimana sebaiknya Anda melakukan transaksi perbankan saat berada di tempat umum?', 1, [['Memakai data seluler atau VPN, bukan Wi-Fi umum terbuka', true], ['Memakai Wi-Fi umum apa saja yang gratis', false], ['Meminjam komputer orang lain', false], ['Menyimpan kata sandi di peramban umum', false]]],
                        [QuestionType::TrueFalse, 'Informasi yang viral di media sosial pasti benar.', 1, [['Benar', false], ['Salah', true]]],
                        [QuestionType::ShortText, 'Apa istilah bahasa Inggris untuk penipuan yang memancing korban menyerahkan data pribadi lewat email atau tautan palsu (satu kata)?', 2, [['phishing', true]]],
                    ]],
                ],
            ],
            'ethics' => [
                'title' => 'Tes etika kerja dan penilaian situasi',
                'description' => 'Menguji sikap profesional pada situasi kerja umum: kejujuran, prioritas dan kerja sama.',
                'instructions' => 'Pilih tindakan yang paling tepat menurut Anda. Tidak ada waktu tambahan.',
                'capabilities' => ['Integritas', 'Penentuan prioritas', 'Kerja sama tim'],
                'sections' => [
                    ['title' => 'Situasi kerja', 'instructions' => 'Bayangkan Anda sudah bekerja di perusahaan kami.', 'minutes' => 10, 'questions' => [
                        [QuestionType::SingleChoice, 'Anda menemukan kesalahan pada laporan yang sudah Anda kirim kepada atasan. Apa yang Anda lakukan?', 2, [['Segera memberi tahu atasan dan mengirim laporan yang sudah diperbaiki', true], ['Menunggu sampai ada yang menyadarinya', false], ['Diam karena angkanya hanya selisih sedikit', false], ['Menyalahkan sumber datanya', false]]],
                        [QuestionType::SingleChoice, 'Rekan meminta bantuan saat Anda sedang sibuk dengan tenggat. Apa yang Anda lakukan?', 1, [['Menjelaskan kapan Anda bisa membantu dan menawarkan alternatif', true], ['Menolak tanpa penjelasan', false], ['Mengerjakan tugasnya dan mengabaikan tenggat Anda', false], ['Mengabaikan pesannya', false]]],
                        [QuestionType::SingleChoice, 'Atasan memberi dua tugas mendesak yang bentrok waktunya. Apa langkah Anda?', 2, [['Menanyakan mana yang diprioritaskan dan menyampaikan konsekuensinya', true], ['Mengerjakan yang lebih mudah saja', false], ['Mengerjakan keduanya setengah-setengah', false], ['Menunda keduanya sampai ditagih', false]]],
                        [QuestionType::TrueFalse, 'Menerima hadiah mahal dari vendor yang sedang mengikuti tender wajar dilakukan tanpa melapor.', 1, [['Benar', false], ['Salah', true]]],
                        [QuestionType::MultipleResponse, 'Mana saja sikap kerja sama tim yang baik? Pilih semua yang benar.', 2, [['Membagikan informasi yang dibutuhkan tim', true], ['Mengakui hasil kerja rekan sebagai hasil sendiri', false], ['Memberi umpan balik dengan sopan', true], ['Menunda kabar buruk sampai terlambat', false]]],
                        [QuestionType::Number, 'Rapat dimulai pukul 09.00. Perjalanan biasanya 45 menit dan Anda menambah 20 menit untuk kemacetan. Berapa menit sebelum rapat Anda harus berangkat?', 1, [['65', true]]],
                    ]],
                ],
            ],
        ];
    }
}
