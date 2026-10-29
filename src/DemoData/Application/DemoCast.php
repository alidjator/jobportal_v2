<?php

namespace Src\DemoData\Application;

use Src\Assessment\Domain\PsychologistRole;
use Src\Company\Domain\MemberPreset;
use Src\Job\Domain\WorkMode;
use Src\JobSeeker\Domain\Gender;
use Src\Recruitment\Domain\StageType;

/**
 * The fictitious content of the demo run: every name, email and text is made up. The texts the front end shows (job titles and descriptions, headlines) are
 * Indonesian, as real postings on the platform are. Every email uses the reserved `.test` domain, so nothing can be delivered to a real mailbox.
 */
final class DemoCast
{
    public const string EMAIL_DOMAIN = 'demo.onesis.test';

    /** The language of every account and of the emails the run triggers. */
    public const string LANGUAGE = 'id';

    /** The user agent the audit records of the run carry (there is no IP address). */
    public const string USER_AGENT = 'demo:seed';

    public const string ADMIN_NAME = 'Operator Demo';

    public const string ADMIN_EMAIL = 'admin@'.self::EMAIL_DOMAIN;

    public const string GROUP_NAME = 'Grup Demo Nusantara';

    /** The verified company with one account per preset. */
    public const string SINAR = 'sinar';

    /** The company whose verification documents wait for review, with its company admin only. */
    public const string ARUNIKA = 'arunika';

    /** @var array<string, array{name: string, legal_name: string, admin_name: string, admin_email: string}> */
    public const array COMPANIES = [
        self::SINAR => [
            'name' => 'PT Demo Sinar Teknologi', 'legal_name' => 'PT Demo Sinar Teknologi Indonesia',
            'admin_name' => 'Rina Kusuma', 'admin_email' => 'admin.sinar@'.self::EMAIL_DOMAIN,
        ],
        self::ARUNIKA => [
            'name' => 'PT Demo Arunika Ritel', 'legal_name' => 'PT Demo Arunika Ritel Nusantara',
            'admin_name' => 'Agus Wibowo', 'admin_email' => 'admin.arunika@'.self::EMAIL_DOMAIN,
        ],
    ];

    /**
     * The members of the verified company besides its company admin, one per preset.
     *
     * @return list<array{preset: MemberPreset, name: string, email: string}>
     */
    public static function sinarMembers(): array
    {
        return [
            ['preset' => MemberPreset::Recruiter, 'name' => 'Sari Wulandari', 'email' => 'rekruter.sinar@'.self::EMAIL_DOMAIN],
            ['preset' => MemberPreset::HiringManager, 'name' => 'Hendra Gunawan', 'email' => 'hm.sinar@'.self::EMAIL_DOMAIN],
            ['preset' => MemberPreset::Interviewer, 'name' => 'Maya Putri', 'email' => 'interviewer.sinar@'.self::EMAIL_DOMAIN],
        ];
    }

    /** The partner provider (institution) of the psychologists: its code opens the numbers of its reports. */
    public const string PROVIDER_CODE = 'DEMO';

    public const string PROVIDER_NAME = 'Biro Psikologi Demo Insight';

    public const string PROVIDER_LEGAL_NAME = 'PT Demo Insight Psikologi';

    /** @return list<array{name: string, email: string, role: PsychologistRole}> */
    public static function psychologists(): array
    {
        return [
            ['name' => 'Dr. Laras Maharani, M.Psi.', 'email' => 'psikolog.lead@'.self::EMAIL_DOMAIN, 'role' => PsychologistRole::Lead],
            ['name' => 'Bima Aditya, M.Psi.', 'email' => 'psikolog.anggota@'.self::EMAIL_DOMAIN, 'role' => PsychologistRole::Member],
        ];
    }

    /** @return list<array{name: string, email: string, gender: Gender, birth_date: string, headline: string, last_job: string}> */
    public static function jobSeekers(): array
    {
        $seekers = [
            ['Andi Pratama', Gender::Male, '1997-03-14', 'Backend developer', 'Backend Developer'],
            ['Budi Santoso', Gender::Male, '1995-08-02', 'Analis data dan backend', 'Data Analyst'],
            ['Citra Lestari', Gender::Female, '1998-11-21', 'Software engineer', 'Software Engineer'],
            ['Dewi Anggraini', Gender::Female, '1994-05-09', 'Backend developer berpengalaman', 'Senior Backend Developer'],
            ['Eko Saputra', Gender::Male, '1996-01-30', 'Full-stack developer', 'Full-stack Developer'],
            ['Fitri Handayani', Gender::Female, '1999-07-17', 'Lulusan baru teknik informatika', 'Programmer Magang'],
            ['Gilang Ramadhan', Gender::Male, '1997-12-05', 'Desainer produk digital', 'UI Designer'],
            ['Hana Permata', Gender::Female, '1996-09-26', 'Analis bisnis dan data', 'Business Analyst'],
        ];

        return array_map(static fn (array $seeker): array => [
            'name' => $seeker[0],
            'email' => strtolower(str_replace(' ', '.', $seeker[0])).'@'.self::EMAIL_DOMAIN,
            'gender' => $seeker[1],
            'birth_date' => $seeker[2],
            'headline' => $seeker[3],
            'last_job' => $seeker[4],
        ], $seekers);
    }

    /**
     * The jobs by key. `state` is what the run leaves them in: `active`, `draft` or `closed` (closed after its applications came in).
     *
     * @return array<string, array{company: string, title: string, description: string, work_mode: WorkMode, salary: array{int, int}, vacancies: int, state: string}>
     */
    public static function jobs(): array
    {
        return [
            'backend' => [
                'company' => self::SINAR, 'title' => 'Backend Developer', 'work_mode' => WorkMode::Hybrid, 'salary' => [12_000_000, 18_000_000], 'vacancies' => 2, 'state' => 'active',
                'description' => 'Kami mencari Backend Developer untuk membangun dan merawat layanan API platform kami. Anda akan bekerja dengan PHP, Laravel dan PostgreSQL bersama tim produk.',
            ],
            'uiux' => [
                'company' => self::SINAR, 'title' => 'UI/UX Designer', 'work_mode' => WorkMode::Remote, 'salary' => [9_000_000, 14_000_000], 'vacancies' => 1, 'state' => 'active',
                'description' => 'Rancang pengalaman pengguna yang sederhana dan jelas untuk aplikasi web dan seluler kami, dari riset pengguna sampai prototipe siap dikembangkan.',
            ],
            'data' => [
                'company' => self::SINAR, 'title' => 'Data Analyst', 'work_mode' => WorkMode::Onsite, 'salary' => [10_000_000, 15_000_000], 'vacancies' => 1, 'state' => 'active',
                'description' => 'Olah data operasional menjadi laporan dan dasbor yang membantu tim mengambil keputusan. Pengalaman dengan SQL dan alat visualisasi data diutamakan.',
            ],
            'mobile' => [
                'company' => self::SINAR, 'title' => 'Mobile Developer', 'work_mode' => WorkMode::Hybrid, 'salary' => [12_000_000, 20_000_000], 'vacancies' => 1, 'state' => 'draft',
                'description' => 'Kembangkan aplikasi seluler kami untuk Android dan iOS bersama tim backend dan desain. Lowongan ini masih berupa draf dan belum diterbitkan.',
            ],
            'support' => [
                'company' => self::SINAR, 'title' => 'Customer Support Officer', 'work_mode' => WorkMode::Onsite, 'salary' => [5_500_000, 7_000_000], 'vacancies' => 1, 'state' => 'closed',
                'description' => 'Bantu pelanggan kami lewat telepon, email dan obrolan dengan ramah dan cepat, lalu teruskan masukan mereka kepada tim produk.',
            ],
            'store' => [
                'company' => self::ARUNIKA, 'title' => 'Store Supervisor', 'work_mode' => WorkMode::Onsite, 'salary' => [6_000_000, 8_500_000], 'vacancies' => 2, 'state' => 'active',
                'description' => 'Pimpin tim toko harian kami: jadwal kerja, stok barang, standar pelayanan dan laporan penjualan. Pengalaman di ritel modern menjadi nilai tambah.',
            ],
            'merchandiser' => [
                'company' => self::ARUNIKA, 'title' => 'Merchandiser', 'work_mode' => WorkMode::Onsite, 'salary' => [4_800_000, 6_000_000], 'vacancies' => 3, 'state' => 'active',
                'description' => 'Pastikan produk kami tertata rapi, harga terpasang benar dan stok di rak selalu terisi di toko-toko mitra wilayah Anda.',
            ],
        ];
    }

    /**
     * The applications, in the order they are made: the job seeker (index in `jobSeekers()`), the job key and where the application ends. `withdrawn` is the
     * job seeker's own withdrawal; `hired` is an offer the job seeker accepted. `interviewer` names the member who holds an interview (the interviewer preset when it is left out);
     * the work in the lists of the team (`DemoMyWork`: interviews answered, reviews asked and answered, tests taken and scored) is made on the applications in the last rows.
     *
     * @return list<array{seeker: int, job: string, outcome: StageType, interviewer?: string}>
     */
    public static function applications(): array
    {
        return [
            ['seeker' => 0, 'job' => 'backend', 'outcome' => StageType::Applied],
            ['seeker' => 1, 'job' => 'backend', 'outcome' => StageType::Screening],
            ['seeker' => 2, 'job' => 'backend', 'outcome' => StageType::Interview],
            ['seeker' => 3, 'job' => 'backend', 'outcome' => StageType::Offer],
            ['seeker' => 4, 'job' => 'backend', 'outcome' => StageType::Hired],
            ['seeker' => 5, 'job' => 'backend', 'outcome' => StageType::Rejected],
            ['seeker' => 0, 'job' => 'uiux', 'outcome' => StageType::Withdrawn],
            ['seeker' => 6, 'job' => 'uiux', 'outcome' => StageType::Applied],
            ['seeker' => 1, 'job' => 'data', 'outcome' => StageType::Interview],
            ['seeker' => 7, 'job' => 'data', 'outcome' => StageType::Screening],
            ['seeker' => 2, 'job' => 'support', 'outcome' => StageType::Applied],
            ['seeker' => 3, 'job' => 'store', 'outcome' => StageType::Applied],
            ['seeker' => 4, 'job' => 'store', 'outcome' => StageType::Screening],
            ['seeker' => 5, 'job' => 'merchandiser', 'outcome' => StageType::Applied],
            ['seeker' => 6, 'job' => 'backend', 'outcome' => StageType::Interview],
            ['seeker' => 7, 'job' => 'backend', 'outcome' => StageType::Interview],
            ['seeker' => 5, 'job' => 'data', 'outcome' => StageType::Interview, 'interviewer' => MemberPreset::HiringManager->value],
            ['seeker' => 3, 'job' => 'data', 'outcome' => StageType::Screening],
            ['seeker' => 4, 'job' => 'data', 'outcome' => StageType::Screening],
            ['seeker' => 3, 'job' => 'uiux', 'outcome' => StageType::Assessment],
            ['seeker' => 4, 'job' => 'uiux', 'outcome' => StageType::Assessment],
            ['seeker' => 5, 'job' => 'uiux', 'outcome' => StageType::Assessment],
        ];
    }

    /**
     * The interview kits of the verified company by key: the name and the questions (text, guidance for the interviewers, weight 1 to 5 and the anchors that
     * say what the scores 1, 3 and 5 mean).
     *
     * @return array<string, array{name: string, questions: list<array{text: string, guidance: string, weight: int, anchor_1: string, anchor_3: string, anchor_5: string}>}>
     */
    public static function interviewKits(): array
    {
        return [
            'backend' => ['name' => 'Wawancara teknis Backend Developer', 'questions' => [
                self::question(
                    'Ceritakan layanan API terbesar yang pernah Anda bangun atau rawat: apa tanggung jawab Anda dan bagaimana arsitekturnya?',
                    'Dengarkan peran pribadi kandidat, bukan peran tim. Gali ukuran sistem (permintaan per detik, jumlah pengguna) dan keputusan desain yang ia ambil sendiri.', 4,
                    'Hanya menyebut nama proyek, tidak bisa menjelaskan bagian yang ia kerjakan.', 'Menjelaskan arsitektur dan perannya, tetapi tanpa angka atau alasan keputusan.', 'Menjelaskan arsitektur, angka beban dan alasan tiap keputusan penting dengan jelas.',
                ),
                self::question(
                    'Sebuah endpoint daftar lamaran menjadi lambat setelah datanya membesar. Langkah apa yang Anda ambil untuk menemukan dan memperbaiki penyebabnya?',
                    'Jawaban yang baik dimulai dengan mengukur: rencana query, indeks, masalah N+1, cache. Waspadai jawaban yang langsung loncat ke "tambah server".', 5,
                    'Menebak penyebab tanpa mengukur.', 'Memeriksa query dan indeks, tetapi tidak membuktikan hasil perbaikannya.', 'Mengukur dulu, menemukan penyebab, memperbaiki, lalu membuktikan perbaikan dengan angka.',
                ),
                self::question(
                    'Bagaimana Anda merancang skema basis data untuk lowongan yang punya banyak lamaran, dan bagaimana Anda mengubah skema itu tanpa mengganggu sistem yang berjalan?',
                    'Cari pemahaman relasi, kunci asing, indeks dan migrasi bertahap (menambah kolom lebih dulu, mengisi data, baru menghapus yang lama).', 4,
                    'Mengubah skema langsung di produksi tanpa rencana.', 'Memahami migrasi bertahap, tetapi belum pernah menjalankannya sendiri.', 'Menjelaskan migrasi tanpa henti layanan dengan contoh nyata dan rencana mundur.',
                ),
                self::question(
                    'Bagaimana Anda menguji kode yang Anda tulis? Berikan contoh tes yang pernah menyelamatkan Anda dari kesalahan di produksi.',
                    'Bedakan tes unit, integrasi dan tes alur utama. Kandidat yang baik bisa menyebut kasus nyata, bukan hanya angka cakupan.', 3,
                    'Tidak menulis tes, hanya mencoba manual.', 'Menulis tes untuk fitur baru, tanpa strategi yang jelas.', 'Punya strategi tes berlapis dan contoh nyata tes yang mencegah kegagalan.',
                ),
                self::question(
                    'Bagaimana Anda mengamankan sebuah API yang menerima data pribadi pelamar?',
                    'Cari: autentikasi dan otorisasi per sumber daya, validasi masukan, pembatasan laju, pencatatan akses, enkripsi, dan pemahaman dasar perlindungan data pribadi.', 4,
                    'Hanya menyebut login.', 'Menyebut autentikasi, otorisasi dan validasi, tetapi tidak soal pencatatan atau pembatasan.', 'Menjelaskan pertahanan berlapis dan memberi contoh celah yang pernah ia tutup.',
                ),
                self::question(
                    'Ceritakan saat Anda menerima ulasan kode yang tidak Anda setujui. Apa yang Anda lakukan?',
                    'Pertanyaan perilaku: nilai sikap terbuka dan cara berargumen dengan data, bukan siapa yang menang.', 2,
                    'Mengabaikan atau berdebat tanpa dasar.', 'Mau berdiskusi, tetapi akhirnya mengalah atau memaksakan pendapat.', 'Menyampaikan alasan dengan data, mendengarkan, dan mencapai keputusan yang diterima kedua pihak.',
                ),
            ]],
            'design' => ['name' => 'Wawancara portofolio UI/UX Designer', 'questions' => [
                self::question(
                    'Pilih satu proyek dari portofolio Anda dan ceritakan dari masalah pengguna sampai hasil akhirnya.',
                    'Perhatikan apakah kandidat memulai dari masalah dan pengguna, bukan dari tampilan. Minta ia menunjukkan bagian yang ia kerjakan sendiri.', 5,
                    'Hanya menunjukkan tampilan akhir.', 'Menjelaskan proses, tetapi hasilnya tidak terukur.', 'Menghubungkan masalah, riset, keputusan desain dan dampak yang terukur.',
                ),
                self::question(
                    'Bagaimana Anda melakukan riset pengguna ketika waktu dan anggaran terbatas?',
                    'Cari metode ringan yang realistis: wawancara singkat, uji kegunaan dengan lima orang, analisis data pemakaian.', 4,
                    'Mengandalkan selera sendiri atau atasan.', 'Pernah melakukan riset, tetapi hanya satu metode.', 'Memilih metode sesuai pertanyaan dan menjelaskan apa yang ia pelajari darinya.',
                ),
                self::question(
                    'Tunjukkan satu keputusan desain yang Anda ubah setelah melihat pengguna kesulitan. Apa yang Anda lihat dan apa yang Anda ubah?',
                    'Kandidat yang baik mengakui desain awalnya keliru dan menunjukkan bukti perubahan.', 4,
                    'Tidak pernah mengubah desain setelah rilis.', 'Mengubah desain berdasarkan masukan, tanpa bukti hasilnya.', 'Mengubah desain berdasarkan pengamatan dan mengukur perbaikannya.',
                ),
                self::question(
                    'Bagaimana Anda menjaga konsistensi tampilan dan aksesibilitas (kontras warna, ukuran sentuh, urutan fokus) pada aplikasi yang besar?',
                    'Cari pengalaman dengan sistem desain, komponen bersama dan pedoman aksesibilitas.', 3,
                    'Tidak mempertimbangkan aksesibilitas.', 'Mengetahui pedomannya, tetapi menerapkannya hanya bila diminta.', 'Menerapkan sistem desain dan pemeriksaan aksesibilitas sebagai bagian tetap dari proses.',
                ),
                self::question(
                    'Bagaimana Anda menyerahkan desain kepada developer dan menangani perbedaan antara desain dan hasil jadi?',
                    'Nilai komunikasi dengan developer: spesifikasi yang jelas, keterlibatan saat peninjauan, kesediaan berkompromi.', 3,
                    'Menyerahkan berkas lalu selesai.', 'Mendampingi developer bila ditanya.', 'Bekerja bersama developer sejak awal dan meninjau hasil sebelum rilis.',
                ),
            ]],
            'data' => ['name' => 'Wawancara Data Analyst', 'questions' => [
                self::question(
                    'Ceritakan analisis yang pernah Anda kerjakan dan mengubah sebuah keputusan bisnis.',
                    'Cari rantai lengkap: pertanyaan bisnis, data yang dipakai, temuan, rekomendasi, hasil. Minta angka.', 5,
                    'Menjelaskan laporan tanpa keputusan yang berubah.', 'Ada rekomendasi, tetapi dampaknya tidak diketahui.', 'Analisisnya mengubah keputusan dan dampaknya terukur.',
                ),
                self::question(
                    'Dua tabel, pesanan dan pelanggan, memberi jumlah pelanggan aktif yang berbeda. Bagaimana Anda mencari tahu mana yang benar?',
                    'Cari kebiasaan memeriksa definisi, duplikat, nilai kosong, zona waktu dan penggabungan tabel (join) yang menggandakan baris.', 5,
                    'Memilih angka yang lebih besar atau lebih kecil tanpa memeriksa.', 'Memeriksa definisi atau duplikat, tetapi tidak keduanya.', 'Memeriksa definisi, duplikat, join dan memberi tahu pemilik data tentang temuannya.',
                ),
                self::question(
                    'Jelaskan bagaimana Anda menulis query SQL untuk menghitung berapa banyak pelamar yang berpindah dari satu tahap ke tahap berikutnya setiap minggu.',
                    'Boleh di papan tulis atau dengan kata-kata. Cari penggunaan GROUP BY, fungsi jendela (window function) dan penanganan data yang hilang.', 4,
                    'Tidak bisa menyusun query dasar.', 'Menyusun query yang benar, tetapi tidak efisien atau tidak menangani nilai kosong.', 'Menyusun query yang benar, mudah dibaca dan memperhitungkan kasus tepi.',
                ),
                self::question(
                    'Bagaimana Anda menyajikan temuan yang tidak diharapkan kepada pemimpin yang tidak teknis?',
                    'Nilai kemampuan menyederhanakan, memilih satu pesan utama dan menyebut batas kepastian data.', 3,
                    'Menyajikan semua tabel dan angka.', 'Merangkum dengan jelas, tetapi tidak menyebut keterbatasan data.', 'Satu pesan utama, bukti yang cukup, keterbatasan dan langkah berikutnya.',
                ),
            ]],
            'support' => ['name' => 'Wawancara Customer Support Officer', 'questions' => [
                self::question(
                    'Ceritakan saat Anda menghadapi pelanggan yang sangat kesal. Apa yang Anda lakukan dan bagaimana akhirnya?',
                    'Gunakan pola STAR (situasi, tugas, tindakan, hasil). Nilai empati, ketenangan dan penyelesaian, bukan hanya permintaan maaf.', 5,
                    'Membela diri atau menyerahkan kepada atasan tanpa mencoba.', 'Menenangkan pelanggan, tetapi masalahnya tidak benar-benar selesai.', 'Mendengarkan, mengakui masalah, menyelesaikannya dan menindaklanjuti sampai pelanggan puas.',
                ),
                self::question(
                    'Anda menerima sepuluh keluhan sekaligus dan sebagian mendesak. Bagaimana Anda menentukan urutan pengerjaannya?',
                    'Cari kriteria yang jelas: dampak pada pelanggan, batas waktu, dan komunikasi kepada yang harus menunggu.', 4,
                    'Mengerjakan sesuai urutan masuk saja.', 'Mengutamakan yang mendesak, tetapi tidak memberi tahu yang menunggu.', 'Memakai kriteria jelas dan memberi kabar kepada semua pelanggan.',
                ),
                self::question(
                    'Bagaimana Anda menjelaskan hal yang rumit kepada pelanggan yang tidak paham teknologi? Berikan contoh.',
                    'Minta kandidat menjelaskan sesuatu secara langsung. Nilai bahasa yang sederhana dan sabar.', 4,
                    'Memakai istilah teknis tanpa penjelasan.', 'Menjelaskan dengan bahasa sederhana, tetapi tidak memeriksa pemahaman pelanggan.', 'Menjelaskan sederhana dan memastikan pelanggan paham.',
                ),
                self::question(
                    'Apa yang Anda lakukan ketika Anda tidak tahu jawaban atas pertanyaan pelanggan?',
                    'Jawaban baik: jujur, mencari jawaban yang benar, memberi perkiraan waktu dan menindaklanjuti. Jawaban buruk: menebak.', 3,
                    'Menebak jawaban.', 'Mencari jawaban, tetapi tidak menghubungi pelanggan kembali.', 'Jujur, mencari jawaban, memberi batas waktu dan menghubungi kembali.',
                ),
                self::question(
                    'Mengapa Anda tertarik bekerja di layanan pelanggan, dan bagaimana Anda menjaga semangat setelah hari yang berat?',
                    'Nilai motivasi dan ketahanan. Perhatikan bila kandidat melihat pekerjaan ini hanya sebagai batu loncatan.', 2,
                    'Tidak punya alasan khusus.', 'Menyukai membantu orang, tetapi tidak punya cara menjaga semangat.', 'Termotivasi dan punya kebiasaan yang jelas untuk menjaga diri.',
                ),
            ]],
            'behavioral' => ['name' => 'Wawancara perilaku terstruktur (STAR)', 'questions' => [
                self::question(
                    'Ceritakan saat Anda harus menyelesaikan pekerjaan penting dengan batas waktu yang sangat ketat.',
                    'Minta kandidat mengikuti pola STAR. Gali apa yang ia lakukan sendiri dan apa yang ia korbankan.', 4,
                    'Tidak bisa memberi contoh nyata.', 'Memberi contoh, tetapi tidak jelas peran dan hasilnya.', 'Contoh jelas dengan peran pribadi, pilihan prioritas dan hasil yang terukur.',
                ),
                self::question(
                    'Ceritakan kegagalan terbesar dalam pekerjaan Anda dan apa yang Anda pelajari.',
                    'Perhatikan apakah kandidat mengambil tanggung jawab dan mengubah caranya bekerja setelahnya.', 4,
                    'Menyalahkan orang lain atau mengaku tidak pernah gagal.', 'Mengakui kegagalan, tetapi pelajarannya umum.', 'Mengambil tanggung jawab, menjelaskan pelajaran dan perubahan konkret.',
                ),
                self::question(
                    'Ceritakan saat Anda berbeda pendapat dengan rekan kerja atau atasan. Bagaimana Anda menanganinya?',
                    'Nilai cara berkomunikasi dan menghormati orang lain, serta kesediaan menerima keputusan akhir.', 3,
                    'Menghindari atau memaksakan pendapat.', 'Menyampaikan pendapat, tetapi hubungan kerja terganggu.', 'Menyampaikan dengan data, mendengarkan dan mencapai keputusan bersama.',
                ),
                self::question(
                    'Ceritakan saat Anda harus mempelajari sesuatu yang baru dengan cepat untuk pekerjaan Anda.',
                    'Cari cara belajar yang terarah: sumber yang dipilih, latihan, bertanya kepada orang yang tepat.', 3,
                    'Menunggu diajari.', 'Belajar sendiri, tetapi lambat atau tidak terarah.', 'Belajar dengan cepat, terarah dan menerapkannya dengan hasil nyata.',
                ),
            ]],
            'closing' => ['name' => 'Wawancara akhir dengan hiring manager', 'questions' => [
                self::question(
                    'Apa yang Anda harapkan dari peran ini dalam enam bulan pertama, dan apa yang akan Anda anggap berhasil?',
                    'Cocokkan harapan kandidat dengan peran yang sebenarnya. Catat bila harapannya jauh berbeda.', 4,
                    'Tidak punya gambaran tentang peran.', 'Gambarannya masuk akal, tetapi umum.', 'Gambarannya realistis dan sejalan dengan kebutuhan tim.',
                ),
                self::question(
                    'Gaya kerja dan gaya pimpinan seperti apa yang membuat Anda paling produktif?',
                    'Tidak ada jawaban yang salah. Nilai kecocokan dengan hiring manager dan tim, serta kemampuan beradaptasi.', 3,
                    'Hanya bisa bekerja dengan satu gaya.', 'Punya preferensi jelas, tetapi mau menyesuaikan.', 'Memahami gaya kerjanya sendiri dan mampu menyesuaikan dengan tim.',
                ),
                self::question(
                    'Apa yang masih Anda pertimbangkan tentang tawaran ini, dan kapan Anda dapat mulai bekerja?',
                    'Catat ekspektasi gaji, masa pemberitahuan dan tawaran lain yang sedang berjalan. Jangan menjanjikan angka dalam wawancara.', 2,
                    'Tidak ingin membahas atau jawabannya berubah-ubah.', 'Terbuka, tetapi masih banyak hal belum pasti.', 'Terbuka dan jelas tentang pertimbangan, waktu dan harapan.',
                ),
            ]],
        ];
    }

    /**
     * The default kit of each job's interview stage: the job key and the kit key. The draft job gets its kit too.
     *
     * @return array<string, string>
     */
    public static function jobKits(): array
    {
        return ['backend' => 'backend', 'mobile' => 'backend', 'uiux' => 'design', 'data' => 'data', 'support' => 'support'];
    }

    /** @return array{text: string, guidance: string, weight: int, anchor_1: string, anchor_3: string, anchor_5: string} */
    private static function question(string $text, string $guidance, int $weight, string $anchor1, string $anchor3, string $anchor5): array
    {
        return ['text' => $text, 'guidance' => $guidance, 'weight' => $weight, 'anchor_1' => $anchor1, 'anchor_3' => $anchor3, 'anchor_5' => $anchor5];
    }

    /** @return list<string> every account email of the run except the platform admin */
    public static function userEmails(): array
    {
        return [
            ...array_column(self::COMPANIES, 'admin_email'),
            ...array_column(self::sinarMembers(), 'email'),
            ...array_column(self::psychologists(), 'email'),
            ...array_column(self::jobSeekers(), 'email'),
            ...DemoCampaignContent::registrantEmails(),
        ];
    }
}
