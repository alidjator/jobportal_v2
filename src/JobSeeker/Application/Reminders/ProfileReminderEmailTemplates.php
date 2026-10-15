<?php

namespace Src\JobSeeker\Application\Reminders;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the profile reminders, registered in the shared catalog by the JobSeeker service provider. */
final class ProfileReminderEmailTemplates
{
    public const string PROFILE_REMINDER = 'job_seeker.profile_reminder';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::PROFILE_REMINDER,
                'Sent to a job seeker whose profile is not yet complete enough to apply: 1 day after the first sign-in, then every 7 days, at most 3 times in all. The job seeker can switch it off in the notification preferences.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Siti'),
                    new EmailVariable('missing', 'What is missing, one item per line', "- nomor telepon\n- resume"),
                    new EmailVariable('matching_jobs', 'A sentence about the live jobs that match the job seeker\'s alerts; empty when there are none', 'Saat ini ada 5 lowongan yang cocok dengan alert Anda.'),
                    new EmailVariable('profile_link', 'The link to the profile page; empty when the client page is not configured', 'https://example.test/profile'),
                ],
                TranslatedText::fromArray(['id' => 'Lengkapi profil Anda untuk melamar kerja', 'en' => 'Complete your profile to apply for jobs']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nProfil Anda belum lengkap, sehingga Anda belum bisa melamar. Yang masih perlu dilengkapi:\n\n{{missing}}\n\n{{matching_jobs}}\n\nLengkapi profil Anda di sini: {{profile_link}}\n\nAnda dapat mematikan pengingat ini di pengaturan notifikasi.",
                    'en' => "Hello {{name}},\n\nYour profile is not complete yet, so you cannot apply for jobs. Still to complete:\n\n{{missing}}\n\n{{matching_jobs}}\n\nComplete your profile here: {{profile_link}}\n\nYou can switch these reminders off in your notification preferences.",
                ]),
            ),
        ];
    }
}
