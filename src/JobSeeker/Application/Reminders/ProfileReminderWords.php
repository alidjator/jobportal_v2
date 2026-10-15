<?php

namespace Src\JobSeeker\Application\Reminders;

/** The plain words of a reminder, in the language of the recipient (`id`, anything else reads as `en`). */
final class ProfileReminderWords
{
    private const array MISSING = [
        'phone' => ['id' => 'nomor telepon', 'en' => 'phone number'],
        'location' => ['id' => 'kota', 'en' => 'city'],
        'history' => ['id' => 'pengalaman atau pendidikan', 'en' => 'work experience or education'],
        'resume' => ['id' => 'resume', 'en' => 'resume'],
    ];

    /**
     * @param  list<string>  $missing  codes of `ProfileCompleteness`
     * @return string one `- item` line per missing item
     */
    public static function missing(array $missing, string $locale): string
    {
        $language = $locale === 'id' ? 'id' : 'en';

        return implode("\n", array_map(static fn (string $code): string => '- '.(self::MISSING[$code][$language] ?? $code), $missing));
    }

    /** One sentence about the live jobs the alerts match; empty when there are none. */
    public static function matchingJobs(int $count, string $locale): string
    {
        if ($count < 1) {
            return '';
        }

        return $locale === 'id'
            ? "Saat ini ada {$count} lowongan yang cocok dengan alert Anda."
            : "There are {$count} live jobs that match your alerts right now.";
    }
}
