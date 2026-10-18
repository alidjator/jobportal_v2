<?php

namespace Src\Recruitment\Application;

/** The words of a generated offer letter in the candidate's language (`id` or `en`; any other language reads as English). */
final class OfferLetterLabels
{
    /** @return array<string, string> */
    public static function for(string $language): array
    {
        return $language === 'id' ? self::INDONESIAN : self::ENGLISH;
    }

    /** @return list<string> the twelve month names, January first */
    public static function months(string $language): array
    {
        return $language === 'id'
            ? ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
            : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    }

    private const array INDONESIAN = [
        'title' => 'Surat Penawaran Kerja',
        'dear' => 'Kepada Yth.',
        'intro' => 'Dengan hormat, kami dengan senang hati menawarkan posisi berikut kepada Anda. Syarat penawaran dirinci di bawah ini.',
        'position' => 'Posisi',
        'employment_type' => 'Status kerja',
        'permanent' => 'Karyawan tetap (PKWTT)',
        'contract' => 'Kontrak (PKWT)',
        'internship' => 'Magang',
        'daily' => 'Harian lepas',
        'freelance' => 'Freelance',
        'contract_length' => 'Lama kontrak',
        'months' => 'bulan',
        'probation' => 'Masa percobaan',
        'work_mode' => 'Pola kerja',
        'onsite' => 'Di kantor',
        'hybrid' => 'Hibrida',
        'remote' => 'Jarak jauh',
        'work_location' => 'Lokasi kerja',
        'start_date' => 'Tanggal mulai',
        'salary' => 'Gaji pokok per bulan',
        'gross' => 'bruto',
        'net' => 'neto',
        'allowances' => 'Tunjangan per bulan',
        'allowances_total' => 'Total tunjangan',
        'benefits' => 'Benefit',
        'note' => 'Catatan',
        'deadline' => 'Mohon jawab penawaran ini sebelum',
        'issued_by' => 'Diterbitkan oleh %s melalui %s',
    ];

    private const array ENGLISH = [
        'title' => 'Offer Letter',
        'dear' => 'Dear',
        'intro' => 'We are pleased to offer you the position below. The terms of the offer are set out in this letter.',
        'position' => 'Position',
        'employment_type' => 'Employment type',
        'permanent' => 'Permanent employee (PKWTT)',
        'contract' => 'Fixed-term contract (PKWT)',
        'internship' => 'Internship',
        'daily' => 'Daily worker',
        'freelance' => 'Freelance',
        'contract_length' => 'Contract length',
        'months' => 'months',
        'probation' => 'Probation',
        'work_mode' => 'Work mode',
        'onsite' => 'On site',
        'hybrid' => 'Hybrid',
        'remote' => 'Remote',
        'work_location' => 'Work location',
        'start_date' => 'Start date',
        'salary' => 'Base salary per month',
        'gross' => 'gross',
        'net' => 'net',
        'allowances' => 'Allowances per month',
        'allowances_total' => 'Total allowances',
        'benefits' => 'Benefits',
        'note' => 'Note',
        'deadline' => 'Please answer this offer before',
        'issued_by' => 'Issued by %s through %s',
    ];
}
