<?php

namespace Src\Matching\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Matching context, registered in the shared catalog by its service provider. The body carries no link (no client page exists yet). */
final class RecommendationEmailTemplates
{
    public const string RECOMMENDATIONS = 'matching.recommendations';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::RECOMMENDATIONS,
                'Sent every Monday at 08:00 (Jakarta time) to a job seeker with up to 5 live jobs that fit their profile and were not recommended before.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Siti'),
                    new EmailVariable('job_count', 'How many jobs are listed', '3'),
                    new EmailVariable('jobs', 'The jobs, one per line, each with its match percentage', "Backend Developer (82% match)\n\nData Engineer (64% match)"),
                ],
                TranslatedText::fromArray(['id' => 'Lowongan yang cocok untuk Anda', 'en' => 'Jobs that fit you']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nBerdasarkan profil Anda, {{job_count}} lowongan ini mungkin cocok:\n\n{{jobs}}\n\nBuka akun Anda untuk melihat detailnya. Anda dapat mematikan email ini di pengaturan notifikasi.",
                    'en' => "Hello {{name}},\n\nBased on your profile, these {{job_count}} jobs may suit you:\n\n{{jobs}}\n\nOpen your account to see the details. You can switch this email off in your notification settings.",
                ]),
            ),
        ];
    }
}
