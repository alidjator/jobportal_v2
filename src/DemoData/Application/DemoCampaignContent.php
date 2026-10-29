<?php

namespace Src\DemoData\Application;

use Src\Campaign\Domain\CampaignType;

/**
 * The fictitious campaigns of the demo ({@see DemoCampaigns}): the sources that bring job seekers to the platform, their periods (counted in days from the day of the run, so there is always one running,
 * one that has not started, one that ended and one that is switched off), and the job seekers who signed up through each. Every name is made up.
 */
final class DemoCampaignContent
{
    /**
     * By code (lower case, unique): the name, the type, the owner (`null` is the platform, otherwise the key of a company of the demo), the start and the end in days from today (`null`: open) and whether it is on.
     *
     * @return array<string, array{name: string, type: CampaignType, owner: string|null, from: int|null, to: int|null, active: bool}>
     */
    public static function campaigns(): array
    {
        return [
            'bursa-kerja-ui-2026' => ['name' => 'Bursa Kerja Universitas Indonesia 2026', 'type' => CampaignType::JobFair, 'owner' => null, 'from' => -20, 'to' => 25, 'active' => true],
            'itb-career-day' => ['name' => 'Career Day Institut Teknologi Bandung', 'type' => CampaignType::EducationInstitution, 'owner' => DemoCast::SINAR, 'from' => -14, 'to' => 16, 'active' => true],
            'disnaker-dki-2026' => ['name' => 'Program Penempatan Dinas Tenaga Kerja DKI Jakarta', 'type' => CampaignType::GovernmentInstitution, 'owner' => null, 'from' => -120, 'to' => -45, 'active' => true],
            'bbpvp-bekasi' => ['name' => 'Alumni Pelatihan BBPVP Bekasi', 'type' => CampaignType::TrainingInstitution, 'owner' => null, 'from' => -30, 'to' => null, 'active' => true],
            'komunitas-php' => ['name' => 'Komunitas PHP Indonesia', 'type' => CampaignType::Partner, 'owner' => DemoCast::SINAR, 'from' => -10, 'to' => null, 'active' => true],
            'papan-loker-nasional' => ['name' => 'Papan Loker Nasional', 'type' => CampaignType::JobMarketplace, 'owner' => DemoCast::ARUNIKA, 'from' => -25, 'to' => 35, 'active' => true],
            'instagram-akhir-tahun' => ['name' => 'Kampanye Instagram Rekrutmen Akhir Tahun', 'type' => CampaignType::SocialMedia, 'owner' => DemoCast::ARUNIKA, 'from' => 10, 'to' => 40, 'active' => true],
            'webinar-karier-q3' => ['name' => 'Webinar Karier Kuartal Ketiga', 'type' => CampaignType::SocialMedia, 'owner' => null, 'from' => -70, 'to' => -40, 'active' => false],
        ];
    }

    /**
     * The campaign each of the job seekers of `DemoCast::jobSeekers()` (by index) signed up through, so their applications count in its results; the others came on their own.
     *
     * @return array<int, string>
     */
    public static function seekerCampaigns(): array
    {
        return [0 => 'bursa-kerja-ui-2026', 1 => 'itb-career-day', 2 => 'bursa-kerja-ui-2026', 3 => 'komunitas-php', 5 => 'bbpvp-bekasi', 6 => 'itb-career-day'];
    }

    /**
     * The job seekers who only signed up through a campaign (no profile, no application), by campaign code. A campaign that is not running cannot take a sign-up, so only the running ones have any.
     *
     * @return array<string, list<string>>
     */
    public static function registrants(): array
    {
        return [
            'bursa-kerja-ui-2026' => ['Aditya Nugraha', 'Bella Safitri', 'Candra Wijaya', 'Diah Puspita', 'Erlangga Putra', 'Farah Nabila', 'Galih Prasetyo', 'Hesti Rahayu', 'Ilham Maulana'],
            'itb-career-day' => ['Jasmine Aulia', 'Kevin Setiawan', 'Lestari Dewi', 'Muhamad Rizky', 'Nadia Kartika', 'Oscar Firmansyah'],
            'bbpvp-bekasi' => ['Putri Ramadani', 'Qori Hidayat', 'Rangga Saputra', 'Salsabila Zahra', 'Taufik Hidayah'],
            'komunitas-php' => ['Umar Faruq', 'Vina Oktaviani', 'Wahyu Pratama', 'Yoga Aditama'],
            'papan-loker-nasional' => ['Zahra Amelia', 'Arif Budiman', 'Bunga Maharani', 'Cahyo Nugroho', 'Dinda Anggraeni', 'Fajar Kurniawan', 'Gita Permatasari'],
        ];
    }

    /** @return list<string> the emails of every campaign registrant, for the check that the demo data is not there yet */
    public static function registrantEmails(): array
    {
        return array_map(self::email(...), array_merge(...array_values(self::registrants())));
    }

    public static function email(string $name): string
    {
        return strtolower(str_replace(' ', '.', $name)).'@'.DemoCast::EMAIL_DOMAIN;
    }
}
