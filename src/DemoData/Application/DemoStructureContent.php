<?php

namespace Src\DemoData\Application;

use Src\Organization\Domain\LegalForm;

/**
 * The organisation structure of the demo group (`docs/design/phase-8-demo-data.md`): six legal entities with their unit trees, cost centres and positions. All names are
 * fictitious; the texts are Indonesian as the real ones would be, codes and comments are English. A parent, a unit and a reporting position always come before what refers to them,
 * because the builder creates the rows in the order written here.
 */
final class DemoStructureContent
{
    /** The legal entities the demo group gets besides the two companies' own, which carry the keys `sinar` and `arunika`. */
    public const array EXTRA_ENTITIES = [
        'holding' => ['legal_name' => 'PT Demo Nusantara Holding Tbk', 'legal_form' => LegalForm::PtTbk],
        'logistik' => ['legal_name' => 'PT Demo Logistik Samudra', 'legal_form' => LegalForm::Pt],
        'finansial' => ['legal_name' => 'PT Demo Finansial Mandiri', 'legal_form' => LegalForm::Pt],
        'yayasan' => ['legal_name' => 'Yayasan Demo Pendidikan Nusantara', 'legal_form' => LegalForm::Yayasan],
    ];

    /** The unit types of the group: code, name and level order (the order only sorts and labels). */
    public const array UNIT_TYPES = [
        ['DIR', 'Direktorat', 1],
        ['DIV', 'Divisi', 2],
        ['DEP', 'Departemen', 3],
        ['CAB', 'Cabang atau toko', 4],
        ['SEK', 'Seksi', 5],
    ];

    /** The grades of the group: code, name and rank (a higher rank is a higher grade). */
    public const array GRADES = [
        ['G1', 'Pelaksana', 1],
        ['G2', 'Staf', 2],
        ['G3', 'Staf senior', 3],
        ['G4', 'Supervisor', 4],
        ['G5', 'Asisten manajer', 5],
        ['G6', 'Manajer', 6],
        ['G7', 'Manajer senior', 7],
        ['G8', 'Kepala divisi', 8],
        ['G9', 'Direktur', 9],
    ];

    /**
     * Per entity key: the cost centres as `[code, name]`, the units as `[code, name, parent code, unit type, cost centre code]` and the positions as
     * `[code, title, unit code, lowest grade, highest grade, reports-to position code]`.
     *
     * @return array<string, array{cost_centres: list<array{0: string, 1: string}>, units: list<array{0: string, 1: string, 2: string|null, 3: string, 4: string|null}>, positions: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string|null}>}>
     */
    public static function entities(): array
    {
        return [
            'sinar' => self::sinar(),
            'arunika' => self::arunika(),
            'holding' => self::holding(),
            'logistik' => self::logistik(),
            'finansial' => self::finansial(),
            'yayasan' => self::yayasan(),
        ];
    }

    /** A technology company: the jobs of the demo (backend, mobile, UI/UX, data, support) have their positions here. */
    private static function sinar(): array
    {
        return [
            'cost_centres' => [['CC-GA', 'Umum dan SDM'], ['CC-ENG', 'Rekayasa produk'], ['CC-OPS', 'Operasional TI'], ['CC-SLS', 'Penjualan dan layanan']],
            'units' => [
                ['DIR-UT', 'Direktorat Utama', null, 'DIR', 'CC-GA'],
                ['DIV-SDM', 'Divisi SDM dan Umum', 'DIR-UT', 'DIV', 'CC-GA'],
                ['DEP-REK', 'Departemen Rekrutmen', 'DIV-SDM', 'DEP', 'CC-GA'],
                ['DEP-PKB', 'Departemen Pengembangan Karyawan', 'DIV-SDM', 'DEP', 'CC-GA'],
                ['DEP-UMM', 'Departemen Umum', 'DIV-SDM', 'DEP', 'CC-GA'],
                ['SEK-ARS', 'Seksi Kearsipan', 'DEP-UMM', 'SEK', 'CC-GA'],
                ['DIV-KEU', 'Divisi Keuangan', 'DIR-UT', 'DIV', 'CC-GA'],
                ['DEP-AKT', 'Departemen Akuntansi', 'DIV-KEU', 'DEP', 'CC-GA'],
                ['DEP-PJK', 'Departemen Perpajakan', 'DIV-KEU', 'DEP', 'CC-GA'],
                ['DIR-TKN', 'Direktorat Teknologi', null, 'DIR', 'CC-ENG'],
                ['DIV-ENG', 'Divisi Rekayasa Produk', 'DIR-TKN', 'DIV', 'CC-ENG'],
                ['DEP-BE', 'Departemen Backend', 'DIV-ENG', 'DEP', 'CC-ENG'],
                ['DEP-FE', 'Departemen Frontend dan Mobile', 'DIV-ENG', 'DEP', 'CC-ENG'],
                ['DEP-QA', 'Departemen Quality Assurance', 'DIV-ENG', 'DEP', 'CC-ENG'],
                ['DIV-DAT', 'Divisi Data', 'DIR-TKN', 'DIV', 'CC-ENG'],
                ['DEP-ANL', 'Departemen Analitik', 'DIV-DAT', 'DEP', 'CC-ENG'],
                ['DEP-DEN', 'Departemen Data Engineering', 'DIV-DAT', 'DEP', 'CC-ENG'],
                ['DIV-INF', 'Divisi Infrastruktur dan Keamanan', 'DIR-TKN', 'DIV', 'CC-OPS'],
                ['DEP-DVO', 'Departemen DevOps', 'DIV-INF', 'DEP', 'CC-OPS'],
                ['DEP-SEC', 'Departemen Keamanan Informasi', 'DIV-INF', 'DEP', 'CC-OPS'],
                ['DIR-KOM', 'Direktorat Komersial', null, 'DIR', 'CC-SLS'],
                ['DIV-PJL', 'Divisi Penjualan', 'DIR-KOM', 'DIV', 'CC-SLS'],
                ['DIV-CS', 'Divisi Layanan Pelanggan', 'DIR-KOM', 'DIV', 'CC-SLS'],
                ['DEP-SUP', 'Departemen Dukungan Pelanggan', 'DIV-CS', 'DEP', 'CC-SLS'],
            ],
            'positions' => [
                ['POS-DU', 'Direktur Utama', 'DIR-UT', 'G9', 'G9', null],
                ['POS-DT', 'Direktur Teknologi', 'DIR-TKN', 'G9', 'G9', 'POS-DU'],
                ['POS-DK', 'Direktur Komersial', 'DIR-KOM', 'G9', 'G9', 'POS-DU'],
                ['POS-KSDM', 'Kepala Divisi SDM dan Umum', 'DIV-SDM', 'G8', 'G8', 'POS-DU'],
                ['POS-MREK', 'Manajer Rekrutmen', 'DEP-REK', 'G6', 'G7', 'POS-KSDM'],
                ['POS-TA', 'Talent Acquisition Specialist', 'DEP-REK', 'G3', 'G4', 'POS-MREK'],
                ['POS-MPKB', 'Manajer Pengembangan Karyawan', 'DEP-PKB', 'G6', 'G7', 'POS-KSDM'],
                ['POS-LND', 'Spesialis Pembelajaran dan Pengembangan', 'DEP-PKB', 'G3', 'G4', 'POS-MPKB'],
                ['POS-SPUM', 'Supervisor Umum', 'DEP-UMM', 'G4', 'G5', 'POS-KSDM'],
                ['POS-ARS', 'Staf Kearsipan', 'SEK-ARS', 'G1', 'G2', 'POS-SPUM'],
                ['POS-KKEU', 'Kepala Divisi Keuangan', 'DIV-KEU', 'G8', 'G8', 'POS-DU'],
                ['POS-MAKT', 'Manajer Akuntansi', 'DEP-AKT', 'G6', 'G7', 'POS-KKEU'],
                ['POS-AKT', 'Staf Akuntansi', 'DEP-AKT', 'G2', 'G3', 'POS-MAKT'],
                ['POS-PJK', 'Spesialis Perpajakan', 'DEP-PJK', 'G4', 'G5', 'POS-KKEU'],
                ['POS-KENG', 'Kepala Divisi Rekayasa Produk', 'DIV-ENG', 'G8', 'G8', 'POS-DT'],
                ['POS-MBE', 'Engineering Manager Backend', 'DEP-BE', 'G6', 'G7', 'POS-KENG'],
                ['POS-BE', 'Backend Developer', 'DEP-BE', 'G3', 'G5', 'POS-MBE'],
                ['POS-MFE', 'Engineering Manager Frontend dan Mobile', 'DEP-FE', 'G6', 'G7', 'POS-KENG'],
                ['POS-FE', 'Frontend Developer', 'DEP-FE', 'G3', 'G5', 'POS-MFE'],
                ['POS-MOB', 'Mobile Developer', 'DEP-FE', 'G3', 'G5', 'POS-MFE'],
                ['POS-UX', 'UI/UX Designer', 'DEP-FE', 'G3', 'G5', 'POS-MFE'],
                ['POS-MQA', 'QA Lead', 'DEP-QA', 'G5', 'G6', 'POS-KENG'],
                ['POS-QA', 'QA Engineer', 'DEP-QA', 'G3', 'G4', 'POS-MQA'],
                ['POS-KDAT', 'Kepala Divisi Data', 'DIV-DAT', 'G8', 'G8', 'POS-DT'],
                ['POS-DA', 'Data Analyst', 'DEP-ANL', 'G3', 'G5', 'POS-KDAT'],
                ['POS-DE', 'Data Engineer', 'DEP-DEN', 'G4', 'G5', 'POS-KDAT'],
                ['POS-KINF', 'Kepala Divisi Infrastruktur dan Keamanan', 'DIV-INF', 'G8', 'G8', 'POS-DT'],
                ['POS-DVO', 'DevOps Engineer', 'DEP-DVO', 'G4', 'G5', 'POS-KINF'],
                ['POS-SEC', 'Security Engineer', 'DEP-SEC', 'G4', 'G6', 'POS-KINF'],
                ['POS-KPJL', 'Kepala Divisi Penjualan', 'DIV-PJL', 'G8', 'G8', 'POS-DK'],
                ['POS-AE', 'Account Executive', 'DIV-PJL', 'G3', 'G4', 'POS-KPJL'],
                ['POS-KCS', 'Kepala Layanan Pelanggan', 'DIV-CS', 'G7', 'G8', 'POS-DK'],
                ['POS-CSO', 'Customer Support Officer', 'DEP-SUP', 'G2', 'G3', 'POS-KCS'],
            ],
        ];
    }

    /** A retail chain: stores under regional departments; the Store Supervisor and Merchandiser of the demo jobs have positions here. */
    private static function arunika(): array
    {
        return [
            'cost_centres' => [['CC-OPS', 'Operasional toko'], ['CC-MER', 'Merchandising'], ['CC-SCM', 'Rantai pasok'], ['CC-GA', 'Umum dan SDM']],
            'units' => [
                ['DIR-ARU', 'Direktorat Utama', null, 'DIR', 'CC-GA'],
                ['DIV-OPS', 'Divisi Operasional Toko', 'DIR-ARU', 'DIV', 'CC-OPS'],
                ['DEP-WJB', 'Departemen Wilayah Jabodetabek', 'DIV-OPS', 'DEP', 'CC-OPS'],
                ['CAB-JKP', 'Toko Jakarta Pusat', 'DEP-WJB', 'CAB', 'CC-OPS'],
                ['CAB-JKS', 'Toko Jakarta Selatan', 'DEP-WJB', 'CAB', 'CC-OPS'],
                ['CAB-BDG', 'Toko Bandung', 'DEP-WJB', 'CAB', 'CC-OPS'],
                ['DEP-WJT', 'Departemen Wilayah Jawa Timur', 'DIV-OPS', 'DEP', 'CC-OPS'],
                ['CAB-SBY', 'Toko Surabaya', 'DEP-WJT', 'CAB', 'CC-OPS'],
                ['DIV-MER', 'Divisi Merchandising', 'DIR-ARU', 'DIV', 'CC-MER'],
                ['DEP-BUY', 'Departemen Pembelian', 'DIV-MER', 'DEP', 'CC-MER'],
                ['DEP-VMD', 'Departemen Visual Merchandising', 'DIV-MER', 'DEP', 'CC-MER'],
                ['DIV-SCM', 'Divisi Rantai Pasok', 'DIR-ARU', 'DIV', 'CC-SCM'],
                ['DEP-GUD', 'Departemen Pergudangan', 'DIV-SCM', 'DEP', 'CC-SCM'],
                ['DIV-SDM', 'Divisi SDM dan Umum', 'DIR-ARU', 'DIV', 'CC-GA'],
                ['DIV-KEU', 'Divisi Keuangan', 'DIR-ARU', 'DIV', 'CC-GA'],
            ],
            'positions' => [
                ['POS-DU', 'Direktur Utama', 'DIR-ARU', 'G9', 'G9', null],
                ['POS-KOPS', 'Kepala Divisi Operasional Toko', 'DIV-OPS', 'G8', 'G8', 'POS-DU'],
                ['POS-AMJ', 'Area Manager Jabodetabek', 'DEP-WJB', 'G6', 'G7', 'POS-KOPS'],
                ['POS-AMT', 'Area Manager Jawa Timur', 'DEP-WJT', 'G6', 'G7', 'POS-KOPS'],
                ['POS-SMJKP', 'Store Manager Jakarta Pusat', 'CAB-JKP', 'G5', 'G6', 'POS-AMJ'],
                ['POS-SPJKP', 'Store Supervisor Jakarta Pusat', 'CAB-JKP', 'G4', 'G5', 'POS-SMJKP'],
                ['POS-KSJKP', 'Kasir Jakarta Pusat', 'CAB-JKP', 'G1', 'G2', 'POS-SPJKP'],
                ['POS-SMJKS', 'Store Manager Jakarta Selatan', 'CAB-JKS', 'G5', 'G6', 'POS-AMJ'],
                ['POS-SPJKS', 'Store Supervisor Jakarta Selatan', 'CAB-JKS', 'G4', 'G5', 'POS-SMJKS'],
                ['POS-SMBDG', 'Store Manager Bandung', 'CAB-BDG', 'G5', 'G6', 'POS-AMJ'],
                ['POS-SMSBY', 'Store Manager Surabaya', 'CAB-SBY', 'G5', 'G6', 'POS-AMT'],
                ['POS-SPSBY', 'Store Supervisor Surabaya', 'CAB-SBY', 'G4', 'G5', 'POS-SMSBY'],
                ['POS-KMER', 'Kepala Divisi Merchandising', 'DIV-MER', 'G8', 'G8', 'POS-DU'],
                ['POS-BUY', 'Buyer', 'DEP-BUY', 'G4', 'G5', 'POS-KMER'],
                ['POS-MRC', 'Merchandiser', 'DEP-BUY', 'G2', 'G3', 'POS-KMER'],
                ['POS-VMD', 'Visual Merchandiser', 'DEP-VMD', 'G2', 'G4', 'POS-KMER'],
                ['POS-KSCM', 'Kepala Divisi Rantai Pasok', 'DIV-SCM', 'G8', 'G8', 'POS-DU'],
                ['POS-WHS', 'Warehouse Supervisor', 'DEP-GUD', 'G4', 'G5', 'POS-KSCM'],
                ['POS-STG', 'Staf Gudang', 'DEP-GUD', 'G1', 'G2', 'POS-WHS'],
                ['POS-KSDM', 'Kepala Divisi SDM dan Umum', 'DIV-SDM', 'G8', 'G8', 'POS-DU'],
                ['POS-HRBP', 'HR Business Partner', 'DIV-SDM', 'G4', 'G6', 'POS-KSDM'],
                ['POS-KKEU', 'Kepala Divisi Keuangan', 'DIV-KEU', 'G8', 'G8', 'POS-DU'],
                ['POS-AKT', 'Staf Akuntansi Toko', 'DIV-KEU', 'G2', 'G3', 'POS-KKEU'],
            ],
        ];
    }

    /** A listed holding: a board, the corporate functions and the group-wide services. */
    private static function holding(): array
    {
        return [
            'cost_centres' => [['CC-HQ', 'Kantor pusat holding'], ['CC-FIN', 'Keuangan grup'], ['CC-HRG', 'SDM grup']],
            'units' => [
                ['DIR-UT', 'Direktorat Utama', null, 'DIR', 'CC-HQ'],
                ['DIV-SKP', 'Divisi Sekretaris Perusahaan', 'DIR-UT', 'DIV', 'CC-HQ'],
                ['DIV-AUD', 'Divisi Audit Internal', 'DIR-UT', 'DIV', 'CC-HQ'],
                ['DIR-KEU', 'Direktorat Keuangan', null, 'DIR', 'CC-FIN'],
                ['DIV-TRS', 'Divisi Treasuri', 'DIR-KEU', 'DIV', 'CC-FIN'],
                ['DIV-AKG', 'Divisi Akuntansi Grup', 'DIR-KEU', 'DIV', 'CC-FIN'],
                ['DEP-LKU', 'Departemen Pelaporan Konsolidasi', 'DIV-AKG', 'DEP', 'CC-FIN'],
                ['DIV-HI', 'Divisi Hubungan Investor', 'DIR-KEU', 'DIV', 'CC-FIN'],
                ['DIR-SDM', 'Direktorat SDM dan Umum', null, 'DIR', 'CC-HRG'],
                ['DIV-SDG', 'Divisi SDM Grup', 'DIR-SDM', 'DIV', 'CC-HRG'],
                ['DEP-TLN', 'Departemen Talenta dan Suksesi', 'DIV-SDG', 'DEP', 'CC-HRG'],
                ['DEP-REM', 'Departemen Remunerasi', 'DIV-SDG', 'DEP', 'CC-HRG'],
                ['DIR-STR', 'Direktorat Strategi', null, 'DIR', 'CC-HQ'],
                ['DIV-PKB', 'Divisi Pengembangan Bisnis', 'DIR-STR', 'DIV', 'CC-HQ'],
            ],
            'positions' => [
                ['POS-DU', 'Direktur Utama', 'DIR-UT', 'G9', 'G9', null],
                ['POS-DKEU', 'Direktur Keuangan', 'DIR-KEU', 'G9', 'G9', 'POS-DU'],
                ['POS-DSDM', 'Direktur SDM dan Umum', 'DIR-SDM', 'G9', 'G9', 'POS-DU'],
                ['POS-DSTR', 'Direktur Strategi', 'DIR-STR', 'G9', 'G9', 'POS-DU'],
                ['POS-SEKP', 'Sekretaris Perusahaan', 'DIV-SKP', 'G8', 'G8', 'POS-DU'],
                ['POS-KAUD', 'Kepala Audit Internal', 'DIV-AUD', 'G8', 'G8', 'POS-DU'],
                ['POS-AUD', 'Auditor Internal', 'DIV-AUD', 'G3', 'G5', 'POS-KAUD'],
                ['POS-KTRS', 'Kepala Treasuri', 'DIV-TRS', 'G8', 'G8', 'POS-DKEU'],
                ['POS-KAKG', 'Kepala Akuntansi Grup', 'DIV-AKG', 'G8', 'G8', 'POS-DKEU'],
                ['POS-MLKU', 'Manajer Pelaporan Konsolidasi', 'DEP-LKU', 'G6', 'G7', 'POS-KAKG'],
                ['POS-ALKU', 'Analis Pelaporan Keuangan', 'DEP-LKU', 'G3', 'G5', 'POS-MLKU'],
                ['POS-KHI', 'Kepala Hubungan Investor', 'DIV-HI', 'G8', 'G8', 'POS-DKEU'],
                ['POS-KSDG', 'Kepala SDM Grup', 'DIV-SDG', 'G8', 'G8', 'POS-DSDM'],
                ['POS-MTLN', 'Manajer Talenta dan Suksesi', 'DEP-TLN', 'G6', 'G7', 'POS-KSDG'],
                ['POS-MREM', 'Manajer Remunerasi', 'DEP-REM', 'G6', 'G7', 'POS-KSDG'],
                ['POS-KPKB', 'Kepala Pengembangan Bisnis', 'DIV-PKB', 'G8', 'G8', 'POS-DSTR'],
                ['POS-ABD', 'Analis Pengembangan Bisnis', 'DIV-PKB', 'G3', 'G5', 'POS-KPKB'],
            ],
        ];
    }

    /** A logistics company: fleet, warehouses and the control tower. */
    private static function logistik(): array
    {
        return [
            'cost_centres' => [['CC-ARM', 'Armada'], ['CC-GUD', 'Pergudangan'], ['CC-CTL', 'Control tower'], ['CC-GA', 'Umum dan SDM']],
            'units' => [
                ['DIR-UT', 'Direktorat Utama', null, 'DIR', 'CC-GA'],
                ['DIR-OPS', 'Direktorat Operasi', null, 'DIR', 'CC-ARM'],
                ['DIV-ARM', 'Divisi Armada', 'DIR-OPS', 'DIV', 'CC-ARM'],
                ['DEP-TRK', 'Departemen Truk dan Pengemudi', 'DIV-ARM', 'DEP', 'CC-ARM'],
                ['DEP-MNT', 'Departemen Perawatan Armada', 'DIV-ARM', 'DEP', 'CC-ARM'],
                ['DIV-GUD', 'Divisi Pergudangan', 'DIR-OPS', 'DIV', 'CC-GUD'],
                ['CAB-CKR', 'Gudang Cikarang', 'DIV-GUD', 'CAB', 'CC-GUD'],
                ['CAB-SBY', 'Gudang Surabaya', 'DIV-GUD', 'CAB', 'CC-GUD'],
                ['CAB-MDN', 'Gudang Medan', 'DIV-GUD', 'CAB', 'CC-GUD'],
                ['DIV-CTL', 'Divisi Control Tower', 'DIR-OPS', 'DIV', 'CC-CTL'],
                ['DEP-RUT', 'Departemen Perencanaan Rute', 'DIV-CTL', 'DEP', 'CC-CTL'],
                ['DEP-CS', 'Departemen Layanan Pelanggan', 'DIV-CTL', 'DEP', 'CC-CTL'],
                ['DIV-SDM', 'Divisi SDM dan Keselamatan Kerja', 'DIR-UT', 'DIV', 'CC-GA'],
                ['SEK-K3', 'Seksi Keselamatan Kerja', 'DIV-SDM', 'SEK', 'CC-GA'],
                ['DIV-KEU', 'Divisi Keuangan', 'DIR-UT', 'DIV', 'CC-GA'],
            ],
            'positions' => [
                ['POS-DU', 'Direktur Utama', 'DIR-UT', 'G9', 'G9', null],
                ['POS-DOPS', 'Direktur Operasi', 'DIR-OPS', 'G9', 'G9', 'POS-DU'],
                ['POS-KARM', 'Kepala Divisi Armada', 'DIV-ARM', 'G8', 'G8', 'POS-DOPS'],
                ['POS-MTRK', 'Manajer Armada', 'DEP-TRK', 'G6', 'G7', 'POS-KARM'],
                ['POS-DRV', 'Pengemudi Truk', 'DEP-TRK', 'G1', 'G2', 'POS-MTRK'],
                ['POS-MMNT', 'Manajer Perawatan', 'DEP-MNT', 'G6', 'G7', 'POS-KARM'],
                ['POS-MKN', 'Mekanik', 'DEP-MNT', 'G2', 'G4', 'POS-MMNT'],
                ['POS-KGUD', 'Kepala Divisi Pergudangan', 'DIV-GUD', 'G8', 'G8', 'POS-DOPS'],
                ['POS-MGCKR', 'Manajer Gudang Cikarang', 'CAB-CKR', 'G6', 'G7', 'POS-KGUD'],
                ['POS-SPCKR', 'Supervisor Gudang Cikarang', 'CAB-CKR', 'G4', 'G5', 'POS-MGCKR'],
                ['POS-OPCKR', 'Operator Forklift', 'CAB-CKR', 'G1', 'G2', 'POS-SPCKR'],
                ['POS-MGSBY', 'Manajer Gudang Surabaya', 'CAB-SBY', 'G6', 'G7', 'POS-KGUD'],
                ['POS-MGMDN', 'Manajer Gudang Medan', 'CAB-MDN', 'G6', 'G7', 'POS-KGUD'],
                ['POS-KCTL', 'Kepala Control Tower', 'DIV-CTL', 'G8', 'G8', 'POS-DOPS'],
                ['POS-RUT', 'Perencana Rute', 'DEP-RUT', 'G3', 'G5', 'POS-KCTL'],
                ['POS-CSO', 'Customer Service Officer', 'DEP-CS', 'G2', 'G3', 'POS-KCTL'],
                ['POS-KSDM', 'Kepala SDM dan Keselamatan Kerja', 'DIV-SDM', 'G8', 'G8', 'POS-DU'],
                ['POS-K3', 'Petugas Keselamatan Kerja', 'SEK-K3', 'G3', 'G4', 'POS-KSDM'],
                ['POS-KKEU', 'Kepala Divisi Keuangan', 'DIV-KEU', 'G8', 'G8', 'POS-DU'],
            ],
        ];
    }

    /** A financing company: credit, risk, compliance, marketing and IT. */
    private static function finansial(): array
    {
        return [
            'cost_centres' => [['CC-KRD', 'Kredit'], ['CC-RSK', 'Risiko dan kepatuhan'], ['CC-PMS', 'Pemasaran'], ['CC-TI', 'Teknologi informasi'], ['CC-GA', 'Umum dan SDM']],
            'units' => [
                ['DIR-UT', 'Direktorat Utama', null, 'DIR', 'CC-GA'],
                ['DIV-KRD', 'Divisi Kredit', 'DIR-UT', 'DIV', 'CC-KRD'],
                ['DEP-ANK', 'Departemen Analisis Kredit', 'DIV-KRD', 'DEP', 'CC-KRD'],
                ['DEP-COL', 'Departemen Penagihan', 'DIV-KRD', 'DEP', 'CC-KRD'],
                ['DIV-RSK', 'Divisi Manajemen Risiko', 'DIR-UT', 'DIV', 'CC-RSK'],
                ['DEP-KPT', 'Departemen Kepatuhan', 'DIV-RSK', 'DEP', 'CC-RSK'],
                ['DIV-PMS', 'Divisi Pemasaran', 'DIR-UT', 'DIV', 'CC-PMS'],
                ['DEP-DGT', 'Departemen Pemasaran Digital', 'DIV-PMS', 'DEP', 'CC-PMS'],
                ['CAB-JKT', 'Kantor Cabang Jakarta', 'DIV-PMS', 'CAB', 'CC-PMS'],
                ['CAB-BDG', 'Kantor Cabang Bandung', 'DIV-PMS', 'CAB', 'CC-PMS'],
                ['DIV-TI', 'Divisi Teknologi Informasi', 'DIR-UT', 'DIV', 'CC-TI'],
                ['DEP-SIS', 'Departemen Pengembangan Sistem', 'DIV-TI', 'DEP', 'CC-TI'],
                ['DIV-SDM', 'Divisi SDM dan Umum', 'DIR-UT', 'DIV', 'CC-GA'],
                ['DIV-KEU', 'Divisi Keuangan dan Akuntansi', 'DIR-UT', 'DIV', 'CC-GA'],
            ],
            'positions' => [
                ['POS-DU', 'Direktur Utama', 'DIR-UT', 'G9', 'G9', null],
                ['POS-KKRD', 'Kepala Divisi Kredit', 'DIV-KRD', 'G8', 'G8', 'POS-DU'],
                ['POS-MANK', 'Manajer Analisis Kredit', 'DEP-ANK', 'G6', 'G7', 'POS-KKRD'],
                ['POS-ANK', 'Analis Kredit', 'DEP-ANK', 'G3', 'G5', 'POS-MANK'],
                ['POS-MCOL', 'Manajer Penagihan', 'DEP-COL', 'G6', 'G7', 'POS-KKRD'],
                ['POS-COL', 'Petugas Penagihan', 'DEP-COL', 'G2', 'G3', 'POS-MCOL'],
                ['POS-KRSK', 'Kepala Manajemen Risiko', 'DIV-RSK', 'G8', 'G8', 'POS-DU'],
                ['POS-RSK', 'Analis Risiko', 'DIV-RSK', 'G3', 'G5', 'POS-KRSK'],
                ['POS-MKPT', 'Manajer Kepatuhan', 'DEP-KPT', 'G6', 'G7', 'POS-KRSK'],
                ['POS-KPT', 'Petugas Kepatuhan dan APU PPT', 'DEP-KPT', 'G3', 'G5', 'POS-MKPT'],
                ['POS-KPMS', 'Kepala Divisi Pemasaran', 'DIV-PMS', 'G8', 'G8', 'POS-DU'],
                ['POS-MDGT', 'Manajer Pemasaran Digital', 'DEP-DGT', 'G6', 'G7', 'POS-KPMS'],
                ['POS-DGT', 'Digital Marketing Specialist', 'DEP-DGT', 'G3', 'G5', 'POS-MDGT'],
                ['POS-MCJKT', 'Branch Manager Jakarta', 'CAB-JKT', 'G6', 'G7', 'POS-KPMS'],
                ['POS-MKT', 'Marketing Officer', 'CAB-JKT', 'G2', 'G4', 'POS-MCJKT'],
                ['POS-MCBDG', 'Branch Manager Bandung', 'CAB-BDG', 'G6', 'G7', 'POS-KPMS'],
                ['POS-KTI', 'Kepala Teknologi Informasi', 'DIV-TI', 'G8', 'G8', 'POS-DU'],
                ['POS-DEV', 'Software Engineer', 'DEP-SIS', 'G3', 'G5', 'POS-KTI'],
                ['POS-KSDM', 'Kepala Divisi SDM dan Umum', 'DIV-SDM', 'G8', 'G8', 'POS-DU'],
                ['POS-HR', 'HR Generalist', 'DIV-SDM', 'G3', 'G5', 'POS-KSDM'],
                ['POS-KKEU', 'Kepala Keuangan dan Akuntansi', 'DIV-KEU', 'G8', 'G8', 'POS-DU'],
            ],
        ];
    }

    /** An education foundation: the board, the academy and corporate training. */
    private static function yayasan(): array
    {
        return [
            'cost_centres' => [['CC-AKD', 'Akademik'], ['CC-PLT', 'Pelatihan korporat'], ['CC-GA', 'Umum dan keuangan']],
            'units' => [
                ['DIR-PGR', 'Badan Pengurus', null, 'DIR', 'CC-GA'],
                ['DIV-AKD', 'Divisi Akademik', 'DIR-PGR', 'DIV', 'CC-AKD'],
                ['DEP-KUR', 'Departemen Kurikulum', 'DIV-AKD', 'DEP', 'CC-AKD'],
                ['DEP-PGJ', 'Departemen Pengajaran', 'DIV-AKD', 'DEP', 'CC-AKD'],
                ['DIV-PLT', 'Divisi Pelatihan Korporat', 'DIR-PGR', 'DIV', 'CC-PLT'],
                ['DEP-PRG', 'Departemen Program Pelatihan', 'DIV-PLT', 'DEP', 'CC-PLT'],
                ['DIV-KMH', 'Divisi Kemahasiswaan', 'DIR-PGR', 'DIV', 'CC-AKD'],
                ['SEK-KRR', 'Seksi Pusat Karier', 'DIV-KMH', 'SEK', 'CC-AKD'],
                ['DIV-SDM', 'Divisi SDM dan Umum', 'DIR-PGR', 'DIV', 'CC-GA'],
                ['DIV-KEU', 'Divisi Keuangan', 'DIR-PGR', 'DIV', 'CC-GA'],
            ],
            'positions' => [
                ['POS-KTP', 'Ketua Pengurus', 'DIR-PGR', 'G9', 'G9', null],
                ['POS-KAKD', 'Kepala Divisi Akademik', 'DIV-AKD', 'G8', 'G8', 'POS-KTP'],
                ['POS-MKUR', 'Manajer Kurikulum', 'DEP-KUR', 'G6', 'G7', 'POS-KAKD'],
                ['POS-DSN', 'Dosen dan Instruktur', 'DEP-PGJ', 'G3', 'G6', 'POS-KAKD'],
                ['POS-KPLT', 'Kepala Pelatihan Korporat', 'DIV-PLT', 'G8', 'G8', 'POS-KTP'],
                ['POS-MPRG', 'Manajer Program Pelatihan', 'DEP-PRG', 'G6', 'G7', 'POS-KPLT'],
                ['POS-TRN', 'Fasilitator Pelatihan', 'DEP-PRG', 'G3', 'G5', 'POS-MPRG'],
                ['POS-KKMH', 'Kepala Kemahasiswaan', 'DIV-KMH', 'G7', 'G8', 'POS-KTP'],
                ['POS-KRR', 'Konselor Karier', 'SEK-KRR', 'G3', 'G4', 'POS-KKMH'],
                ['POS-KSDM', 'Kepala SDM dan Umum', 'DIV-SDM', 'G8', 'G8', 'POS-KTP'],
                ['POS-KKEU', 'Kepala Keuangan', 'DIV-KEU', 'G8', 'G8', 'POS-KTP'],
                ['POS-AKT', 'Staf Keuangan', 'DIV-KEU', 'G2', 'G3', 'POS-KKEU'],
            ],
        ];
    }
}
