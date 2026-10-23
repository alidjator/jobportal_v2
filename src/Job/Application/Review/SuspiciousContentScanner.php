<?php

namespace Src\Job\Application\Review;

use Src\Job\Application\JobSettings;
use Src\Job\Domain\JobContent;
use Src\Shared\Application\Settings\Settings;

/**
 * Looks in the text of a job for what scam postings carry: a phrase of the admin's list (`jobs.suspicious_phrases`, matched whatever the letter case, on word
 * boundaries) in the title or the description, and a phone number or an email address in the description. A flag sends the job to review and shows the admin
 * what to look at; it never rejects anything by itself. The salary note and the summary of `phase-8-job-discovery.md` are scanned when those fields exist.
 */
final readonly class SuspiciousContentScanner
{
    private const string EMAIL = '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u';

    /** An Indonesian mobile or landline number, or an international one starting with `+`, with spaces, dots or dashes between the groups. */
    private const array PHONES = [
        '/(?<![\d+])(?:\+?62|0)[\s.\-]?8\d{1,3}[\s.\-]?\d{3,4}[\s.\-]?\d{2,5}(?!\d)/',
        '/(?<![\d+])\(?0\d{2,3}\)?[\s.\-]?\d{6,8}(?!\d)/',
        '/(?<!\d)\+\d{1,3}[\s.\-]?\d{2,4}[\s.\-]?\d{3,4}[\s.\-]?\d{3,4}(?!\d)/',
    ];

    public function __construct(private Settings $settings) {}

    /** @return list<JobFlag> */
    public function flags(JobContent $content): array
    {
        return $this->flagsOf($content->title, $content->description, $content->summary, $content->salaryNote);
    }

    /** @return list<JobFlag> */
    public function flagsOf(string $title, ?string $description, ?string $summary = null, ?string $salaryNote = null): array
    {
        $flags = [];
        $fields = ['title' => $title, 'description' => (string) $description, 'summary' => (string) $summary, 'salary_note' => (string) $salaryNote];

        foreach ($this->phrases() as $phrase) {
            $pattern = '/(?<![\p{L}\p{N}])'.str_replace(' ', '\s+', preg_quote($phrase, '/')).'(?![\p{L}\p{N}])/iu';

            foreach ($fields as $field => $text) {
                if ($text !== '' && preg_match($pattern, $text) === 1) {
                    $flags[] = new JobFlag(JobFlag::PHRASE, $phrase, $field);
                }
            }
        }

        $description = $fields['description'];

        if ($description !== '') {
            if (preg_match(self::EMAIL, $description, $match) === 1) {
                $flags[] = new JobFlag(JobFlag::CONTACT, $match[0], 'description');
            }

            foreach (self::PHONES as $pattern) {
                if (preg_match($pattern, $description, $match) === 1) {
                    $flags[] = new JobFlag(JobFlag::CONTACT, trim($match[0]), 'description');

                    break;
                }
            }
        }

        return $flags;
    }

    /** @return list<string> the phrases, one per line of the setting, without empty lines and duplicates */
    private function phrases(): array
    {
        $lines = preg_split('/\R/u', $this->settings->text(JobSettings::SUSPICIOUS_PHRASES)) ?: [];

        return array_values(array_unique(array_filter(array_map(static fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== '')));
    }
}
