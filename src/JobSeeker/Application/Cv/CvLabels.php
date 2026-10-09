<?php

namespace Src\JobSeeker\Application\Cv;

use DateTimeImmutable;

/** The words a CV prints around the job seeker's own text, in the two languages of the platform, and the way it writes months. */
final class CvLabels
{
    public const array LANGUAGES = ['id', 'en'];

    private const array WORDS = [
        'en' => [
            'contact' => 'Contact', 'personal' => 'Personal details', 'summary' => 'Summary', 'experience' => 'Work experience', 'education' => 'Education',
            'skills' => 'Skills', 'languages' => 'Languages', 'links' => 'Links', 'present' => 'Present', 'gpa' => 'GPA',
            'birth_date' => 'Date of birth', 'gender' => 'Gender', 'religion' => 'Religion', 'marital_status' => 'Marital status', 'nationality' => 'Nationality',
            'gender.male' => 'Male', 'gender.female' => 'Female',
            'marital.single' => 'Single', 'marital.married' => 'Married', 'marital.divorced' => 'Divorced', 'marital.widowed' => 'Widowed',
            'proficiency.basic' => 'Basic', 'proficiency.conversational' => 'Conversational', 'proficiency.fluent' => 'Fluent', 'proficiency.native' => 'Native',
            'link.linkedin' => 'LinkedIn', 'link.github' => 'GitHub', 'link.portfolio' => 'Portfolio', 'link.website' => 'Website', 'link.instagram' => 'Instagram',
            'link.x' => 'X', 'link.other' => 'Other',
        ],
        'id' => [
            'contact' => 'Kontak', 'personal' => 'Data pribadi', 'summary' => 'Ringkasan', 'experience' => 'Pengalaman kerja', 'education' => 'Pendidikan',
            'skills' => 'Keahlian', 'languages' => 'Bahasa', 'links' => 'Tautan', 'present' => 'Sekarang', 'gpa' => 'IPK',
            'birth_date' => 'Tanggal lahir', 'gender' => 'Jenis kelamin', 'religion' => 'Agama', 'marital_status' => 'Status pernikahan', 'nationality' => 'Kebangsaan',
            'gender.male' => 'Laki-laki', 'gender.female' => 'Perempuan',
            'marital.single' => 'Belum menikah', 'marital.married' => 'Menikah', 'marital.divorced' => 'Cerai hidup', 'marital.widowed' => 'Cerai mati',
            'proficiency.basic' => 'Dasar', 'proficiency.conversational' => 'Percakapan', 'proficiency.fluent' => 'Lancar', 'proficiency.native' => 'Bahasa ibu',
            'link.linkedin' => 'LinkedIn', 'link.github' => 'GitHub', 'link.portfolio' => 'Portofolio', 'link.website' => 'Situs web', 'link.instagram' => 'Instagram',
            'link.x' => 'X', 'link.other' => 'Lainnya',
        ],
    ];

    private const array SHORT_MONTHS = [
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'id' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
    ];

    private const array LONG_MONTHS = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'id' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
    ];

    /** @return array<string, string> every label of the language */
    public static function words(string $language): array
    {
        return self::WORDS[$language] ?? self::WORDS['en'];
    }

    /** "Mar 2022" (`Mar 2022` in English, `Mar 2022` in Indonesian with Indonesian month names). */
    public static function month(DateTimeImmutable $date, string $language): string
    {
        return (self::SHORT_MONTHS[$language] ?? self::SHORT_MONTHS['en'])[(int) $date->format('n') - 1].' '.$date->format('Y');
    }

    /** "10 May 1998" / "10 Mei 1998". */
    public static function day(DateTimeImmutable $date, string $language): string
    {
        return $date->format('j').' '.(self::LONG_MONTHS[$language] ?? self::LONG_MONTHS['en'])[(int) $date->format('n') - 1].' '.$date->format('Y');
    }
}
