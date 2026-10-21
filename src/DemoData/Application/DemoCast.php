<?php

namespace Src\DemoData\Application;

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
     * job seeker's own withdrawal; `hired` is an offer the job seeker accepted.
     *
     * @return list<array{seeker: int, job: string, outcome: StageType}>
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
        ];
    }

    /** @return list<string> every account email of the run except the platform admin */
    public static function userEmails(): array
    {
        return [
            ...array_column(self::COMPANIES, 'admin_email'),
            ...array_column(self::sinarMembers(), 'email'),
            ...array_column(self::jobSeekers(), 'email'),
        ];
    }
}
