<?php

namespace Src\Dashboard\Application;

/** The wording of one digest line per kind of thing that waits, in the languages of the platform. A line reads "{count} {words}"; a key without wording falls back to its own name. */
final class TeamDigestLines
{
    private const array TASKS = [
        'stale_applications' => ['id' => 'lamaran menunggu terlalu lama di tahap awal', 'en' => 'applications waiting too long in an early stage'],
        'unscheduled_interviews' => ['id' => 'lamaran di tahap wawancara yang belum dijadwalkan', 'en' => 'applications in an interview stage with no interview scheduled'],
        'my_scorecards' => ['id' => 'kartu penilaian wawancara menunggu Anda', 'en' => 'interview scorecards waiting for you'],
        'my_scoring' => ['id' => 'tes menunggu penilaian Anda', 'en' => 'tests waiting for your scoring'],
        'offers_waiting' => ['id' => 'penawaran belum dijawab', 'en' => 'offers not answered yet'],
        'offers_expiring' => ['id' => 'penawaran segera berakhir', 'en' => 'offers about to expire'],
        'jobs_closing' => ['id' => 'lowongan segera ditutup', 'en' => 'jobs closing soon'],
        'jobs_draft' => ['id' => 'lowongan masih draf', 'en' => 'jobs still in draft'],
        'jobs_without_applicants' => ['id' => 'lowongan aktif tanpa pelamar', 'en' => 'active jobs without applicants'],
    ];

    public static function task(string $key, int $count, string $language): string
    {
        return $count.' '.(self::TASKS[$key][self::language($language)] ?? $key);
    }

    public static function newApplications(int $count, string $language): string
    {
        return $count.' '.(self::language($language) === 'id' ? 'lamaran baru dalam 24 jam terakhir' : ($count === 1 ? 'new application' : 'new applications').' in the last 24 hours');
    }

    public static function interview(string $time, ?string $candidate, ?string $job, string $language): string
    {
        $who = trim(($candidate ?? '').($job !== null ? " ({$job})" : ''));

        return (self::language($language) === 'id' ? 'Wawancara' : 'Interview').' '.$time.($who === '' ? '' : ' - '.$who);
    }

    private static function language(string $language): string
    {
        return $language === 'en' ? 'en' : 'id';
    }
}
