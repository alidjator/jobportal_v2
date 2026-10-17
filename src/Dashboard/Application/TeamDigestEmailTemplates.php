<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the daily team digest (`docs/design/phase-8-recruiter-workday.md` section 3), registered in the shared catalog by the Dashboard service provider. */
final class TeamDigestEmailTemplates
{
    public const string DAILY_DIGEST = 'team.daily_digest';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::DAILY_DIGEST,
                'Sent every morning to a company member who has something waiting: their task list, the applications of the last 24 hours and the interviews of the day.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Budi'),
                    new EmailVariable('company', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('date', 'Today, in Jakarta', '17 Oktober 2026'),
                    new EmailVariable('summary', 'One line per thing that waits, each with its link when the company app is configured', "3 lamaran baru dalam 24 jam terakhir\n\n2 lamaran menunggu terlalu lama di tahap awal"),
                ],
                TranslatedText::fromArray(['id' => 'Ringkasan harian {{company}}, {{date}}', 'en' => 'Daily summary of {{company}}, {{date}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nIni yang menunggu Anda di {{company}} hari ini:\n\n{{summary}}",
                    'en' => "Hello {{name}},\n\nThis is what waits for you at {{company}} today:\n\n{{summary}}",
                ]),
            ),
        ];
    }
}
