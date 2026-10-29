<?php

namespace Src\DemoData\Application;

use Src\Workforce\Domain\RequisitionType;

/**
 * The content of the demo workforce ({@see DemoWorkforce}): the people who act, the manpower plan lines of the two tenants and the requisitions, each with the path it takes through approval.
 * Every position code is one of {@see DemoStructureContent}. Money is in rupiah; every name is made up.
 */
final class DemoWorkforceContent
{
    /** The people who act in the demo: Hendra requests, Sari and Maya are hiring managers, Rina approves and plans, Agus is the other tenant's admin. */
    public const string RINA = 'rina';

    public const string HENDRA = 'hendra';

    public const string SARI = 'sari';

    public const string MAYA = 'maya';

    public const string AGUS = 'agus';

    /** How many days the delegation of Hendra to Sari lasts, from today. */
    public const int DELEGATION_DAYS = 14;

    /**
     * Plan lines as `[position code, opening headcount, year-end target, annual cost of one hire]`; the additions are spread over the months and the cost of the line is the additions times the annual cost.
     *
     * @return array<string, array<string, list<array{0: string, 1: int, 2: int, 3: int}>>> by tenant key, then by plan key
     */
    public static function plans(): array
    {
        return [
            DemoCast::SINAR => [
                // The plan in force of the current fiscal year, the revision of it that waits for approval, and the draft of the next year.
                'current' => [
                    ['POS-BE', 6, 9, 216_000_000], ['POS-FE', 4, 6, 180_000_000], ['POS-MOB', 2, 3, 192_000_000], ['POS-UX', 2, 4, 168_000_000], ['POS-QA', 3, 5, 144_000_000],
                    ['POS-DA', 3, 7, 180_000_000], ['POS-DVO', 2, 3, 192_000_000], ['POS-SEC', 1, 2, 216_000_000], ['POS-TA', 2, 3, 120_000_000], ['POS-CSO', 8, 8, 66_000_000],
                ],
                'revision' => [
                    ['POS-BE', 6, 10, 216_000_000], ['POS-FE', 4, 6, 180_000_000], ['POS-MOB', 2, 3, 192_000_000], ['POS-UX', 2, 4, 168_000_000], ['POS-QA', 3, 5, 144_000_000],
                    ['POS-DA', 3, 7, 180_000_000], ['POS-DVO', 2, 3, 192_000_000], ['POS-SEC', 1, 2, 216_000_000], ['POS-TA', 2, 3, 120_000_000], ['POS-CSO', 8, 8, 66_000_000],
                    ['POS-DE', 1, 3, 228_000_000],
                ],
                'next' => [
                    ['POS-BE', 10, 12, 228_000_000], ['POS-FE', 6, 8, 190_000_000], ['POS-DA', 7, 9, 190_000_000], ['POS-CSO', 8, 10, 70_000_000],
                ],
            ],
            DemoCast::ARUNIKA => [
                'current' => [
                    ['POS-MRC', 10, 14, 66_000_000], ['POS-SPJKP', 2, 3, 84_000_000], ['POS-SPSBY', 1, 2, 84_000_000], ['POS-WHS', 3, 4, 78_000_000], ['POS-VMD', 2, 3, 72_000_000],
                ],
                'next' => [
                    ['POS-MRC', 14, 16, 70_000_000], ['POS-WHS', 4, 5, 82_000_000], ['POS-BUY', 3, 4, 90_000_000],
                ],
            ],
        ];
    }

    /**
     * The requisitions, in the order they are made. `days` is the target join date counted from today (and kept inside the fiscal year, so the plan line of that year is the quota); `by` is who
     * writes it; `manager` the hiring manager; `path` what happens to it: `submit`, `approve` (who), `reject` and `return` (who, why), `open_job` (who, hires), `close` (who, why), `cancel`, `amend` (hires,
     * salary range, why) and `approve_amendment` (who).
     *
     * @return list<array{
     *     tenant: string, type: RequisitionType, position: string, grade: string|null, hires: int, job_type: string, contract_months: int|null, days: int, salary: array{int|null, int|null},
     *     justification: string, replaces: array{string, int}|null, by: string, manager: string, path: list<list<int|string|null>>
     * }>
     */
    public static function requisitions(): array
    {
        return [
            // Sinar. A planned requisition that fits its plan line and is small: only the hiring manager approves; one job is opened of its two hires.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-BE', 'grade' => 'G4', 'hires' => 2, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 45,
                'salary' => [12_000_000, 18_000_000], 'justification' => 'Menambah kapasitas tim backend untuk layanan API baru yang dijadwalkan rilis pada kuartal depan.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::SARI, 'path' => [['submit'], ['approve', self::SARI], ['open_job', self::RINA, 1]],
            ],
            // Waiting for the hiring manager.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-UX', 'grade' => 'G4', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 60,
                'salary' => [9_000_000, 14_000_000], 'justification' => 'Desainer kedua dibutuhkan untuk mendampingi riset pengguna dan sistem desain.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::SARI, 'path' => [['submit']],
            ],
            // Unplanned and above the budget of 20 million: the hiring manager has approved, the group workforce role has not.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Unplanned, 'position' => 'POS-SEC', 'grade' => 'G5', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 50,
                'salary' => [18_000_000, 25_000_000], 'justification' => 'Hasil audit keamanan meminta satu insinyur keamanan tambahan di luar rencana tahunan.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::SARI, 'path' => [['submit'], ['approve', self::SARI]],
            ],
            // A replacement on a contract: approved by the hiring manager, a job opened, then closed because the seat is filled.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Replacement, 'position' => 'POS-CSO', 'grade' => null, 'hires' => 1, 'job_type' => 'contract', 'contract_months' => 12, 'days' => 30,
                'salary' => [5_500_000, 7_000_000], 'justification' => 'Menggantikan petugas yang mengundurkan diri; layanan pelanggan tidak boleh kosong.', 'replaces' => ['Dimas Prakoso', 21],
                'by' => self::HENDRA, 'manager' => self::MAYA,
                'path' => [['submit'], ['approve', self::MAYA], ['open_job', self::RINA, 1], ['close', self::RINA, 'Posisi sudah terisi oleh kandidat internal.']],
            ],
            // Three hires: the hiring manager and then the company admin approve it; an amendment for a fourth hire has the first approval and waits for the second.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-DA', 'grade' => 'G4', 'hires' => 3, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 40,
                'salary' => [10_000_000, 15_000_000], 'justification' => 'Divisi Data menambah analis untuk dasbor operasional dan laporan keuangan bulanan.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::SARI,
                'path' => [['submit'], ['approve', self::SARI], ['approve', self::RINA], ['amend', 4, null, null, 'Beban laporan naik, satu analis lagi dibutuhkan.'], ['approve_amendment', self::SARI]],
            ],
            // Rejected by the hiring manager.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-MOB', 'grade' => 'G4', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 70,
                'salary' => [12_000_000, 20_000_000], 'justification' => 'Pengembang seluler untuk aplikasi pelamar versi baru.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::SARI, 'path' => [['submit'], ['reject', self::SARI, 'Pengembangan aplikasi seluler ditunda sampai paruh kedua tahun.']],
            ],
            // A draft that was never sent.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-FE', 'grade' => 'G4', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 80,
                'salary' => [11_000_000, 16_000_000], 'justification' => 'Frontend developer untuk portal pelanggan.', 'replaces' => null, 'by' => self::HENDRA, 'manager' => self::SARI, 'path' => [],
            ],
            // Returned for revision: it is a draft again with the reason of the hiring manager.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-QA', 'grade' => 'G3', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 55,
                'salary' => [8_000_000, 12_000_000], 'justification' => 'QA engineer tambahan.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::SARI, 'path' => [['submit'], ['return', self::SARI, 'Mohon jelaskan beban pengujian yang membutuhkan tambahan orang.']],
            ],
            // Cancelled by the requester before anyone decided.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Unplanned, 'position' => 'POS-AE', 'grade' => 'G3', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 65,
                'salary' => [7_000_000, 10_000_000], 'justification' => 'Account executive untuk segmen usaha kecil.', 'replaces' => null,
                'by' => self::HENDRA, 'manager' => self::MAYA, 'path' => [['submit'], ['cancel']],
            ],
            // Written by the company admin herself; her hiring manager approves it.
            [
                'tenant' => DemoCast::SINAR, 'type' => RequisitionType::Planned, 'position' => 'POS-TA', 'grade' => 'G3', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 35,
                'salary' => [8_000_000, 12_000_000], 'justification' => 'Talent acquisition specialist untuk rekrutmen divisi teknologi.', 'replaces' => null,
                'by' => self::RINA, 'manager' => self::HENDRA, 'path' => [['submit'], ['approve', self::HENDRA]],
            ],
            // Arunika has no flow of its own: the group flow is one step, the holders of the group workforce role.
            [
                'tenant' => DemoCast::ARUNIKA, 'type' => RequisitionType::Planned, 'position' => 'POS-MRC', 'grade' => 'G2', 'hires' => 2, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 30,
                'salary' => [4_800_000, 6_000_000], 'justification' => 'Dua merchandiser untuk pembukaan toko Jakarta Selatan.', 'replaces' => null,
                'by' => self::AGUS, 'manager' => self::AGUS, 'path' => [['submit'], ['approve', self::RINA], ['open_job', self::AGUS, 2]],
            ],
            [
                'tenant' => DemoCast::ARUNIKA, 'type' => RequisitionType::Unplanned, 'position' => 'POS-SPSBY', 'grade' => null, 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 45,
                'salary' => [6_000_000, 8_500_000], 'justification' => 'Supervisor toko Surabaya mengundurkan diri lebih awal dari perkiraan.', 'replaces' => null,
                'by' => self::AGUS, 'manager' => self::AGUS, 'path' => [['submit']],
            ],
            [
                'tenant' => DemoCast::ARUNIKA, 'type' => RequisitionType::Planned, 'position' => 'POS-VMD', 'grade' => 'G3', 'hires' => 1, 'job_type' => 'permanent', 'contract_months' => null, 'days' => 75,
                'salary' => [5_000_000, 7_500_000], 'justification' => 'Visual merchandiser untuk kampanye akhir tahun.', 'replaces' => null, 'by' => self::AGUS, 'manager' => self::AGUS, 'path' => [],
            ],
        ];
    }
}
