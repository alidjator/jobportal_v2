<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/**
 * The email templates of the Recruitment context, registered in the shared catalog by its service provider: one per outcome the applicant is told about.
 * Bodies are Markdown with `{{variable}}` placeholders. They never carry the rejection reason or note, and carry no link (no client page exists yet).
 */
final class RecruitmentEmailTemplates
{
    public const string INTERVIEW = 'recruitment.application_interview';

    public const string OFFER = 'recruitment.application_offer';

    public const string HIRED = 'recruitment.application_hired';

    public const string REJECTED = 'recruitment.application_rejected';

    /** The template of the outcome, or null for a stage type the applicant is not emailed about. */
    public static function keyFor(StageType $type): ?string
    {
        return match ($type) {
            StageType::Interview => self::INTERVIEW,
            StageType::Offer => self::OFFER,
            StageType::Hired => self::HIRED,
            StageType::Rejected => self::REJECTED,
            StageType::Applied, StageType::Screening, StageType::Withdrawn => null,
        };
    }

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The applicant\'s name', 'Siti'),
            new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
            new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
        ];

        return [
            new EmailTemplateDefinition(
                self::INTERVIEW,
                'Sent to an applicant when their application moves to the interview stage.',
                $variables,
                TranslatedText::fromArray(['id' => 'Lamaran Anda untuk {{job_title}} maju ke tahap wawancara', 'en' => 'Your application for {{job_title}} moved to the interview stage']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nKabar baik: {{company_name}} memindahkan lamaran Anda untuk posisi {{job_title}} ke tahap wawancara.\n\nBuka akun Anda untuk melihat statusnya. Semoga sukses!",
                    'en' => "Hello {{name}},\n\nGood news: {{company_name}} moved your application for {{job_title}} to the interview stage.\n\nOpen your account to see its status. Good luck!",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OFFER,
                'Sent to an applicant when their application moves to the offer stage.',
                $variables,
                TranslatedText::fromArray(['id' => 'Lamaran Anda untuk {{job_title}} maju ke tahap penawaran', 'en' => 'Your application for {{job_title}} moved to the offer stage']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} memindahkan lamaran Anda untuk posisi {{job_title}} ke tahap penawaran.\n\nBuka akun Anda untuk melihat statusnya.",
                    'en' => "Hello {{name}},\n\n{{company_name}} moved your application for {{job_title}} to the offer stage.\n\nOpen your account to see its status.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::HIRED,
                'Sent to an applicant when their application is marked as hired.',
                $variables,
                TranslatedText::fromArray(['id' => 'Selamat! Anda diterima untuk {{job_title}}', 'en' => 'Congratulations! You were hired for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nSelamat! {{company_name}} menandai lamaran Anda untuk posisi {{job_title}} sebagai diterima.\n\nBuka akun Anda untuk melihat statusnya.",
                    'en' => "Hello {{name}},\n\nCongratulations! {{company_name}} marked your application for {{job_title}} as hired.\n\nOpen your account to see its status.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::REJECTED,
                'Sent to an applicant when their application is rejected. It never states the reason.',
                $variables,
                TranslatedText::fromArray(['id' => 'Pembaruan lamaran Anda untuk {{job_title}}', 'en' => 'An update on your application for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nTerima kasih telah melamar posisi {{job_title}} di {{company_name}}. Setelah ditinjau, mereka tidak melanjutkan lamaran Anda kali ini.\n\nKami berharap Anda segera mendapat peluang yang tepat.",
                    'en' => "Hello {{name}},\n\nThank you for applying for {{job_title}} at {{company_name}}. After review, they will not move forward with your application this time.\n\nWe wish you every success in finding the right opportunity.",
                ]),
            ),
        ];
    }
}
