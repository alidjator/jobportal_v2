<?php

namespace Src\Assessment\Application;

use Src\Assessment\Domain\Events\InvitationChange;
use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Assessment context, registered in the shared catalog by its service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class AssessmentEmailTemplates
{
    public const string PSYCHOLOGIST_INVITATION = 'assessment.psychologist_invitation';

    /** Sent to an assessor when a test they score was submitted (the preference `team_scoring`). */
    public const string TEAM_TEST_SUBMITTED = 'team.test_submitted';

    public const string TEST_INVITED = 'assessment.test_invited';

    public const string TEST_REMINDER = 'assessment.test_reminder';

    public const string TEST_EXPIRED = 'assessment.test_expired';

    public const string TEST_CANCELLED = 'assessment.test_cancelled';

    public const string EXPLANATION_READY = 'assessment.explanation_ready';

    public const string EXPLANATION_REVISED = 'assessment.explanation_revised';

    /** The template that tells the candidate about a change to a test invitation. */
    public static function keyForChange(InvitationChange $change): string
    {
        return match ($change) {
            InvitationChange::Invited => self::TEST_INVITED,
            InvitationChange::Reminder => self::TEST_REMINDER,
            InvitationChange::Expired => self::TEST_EXPIRED,
            InvitationChange::Cancelled => self::TEST_CANCELLED,
        };
    }

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [...self::psychologistDefinitions(), ...self::testDefinitions(), ...self::teamDefinitions()];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function psychologistDefinitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::PSYCHOLOGIST_INVITATION,
                'Sent when a platform admin invites a psychologist to a partner provider, with the link that accepts the invitation.',
                [
                    new EmailVariable('provider', 'The name of the provider that the psychologist joins', 'Lembaga Psikologi Contoh'),
                    new EmailVariable('link', 'The link that accepts the invitation', 'https://example.test/provider/invitations/abc123', required: true),
                    new EmailVariable('days', 'How many days the invitation stays valid', '7'),
                ],
                TranslatedText::fromArray(['id' => 'Undangan psikolog mitra {{provider}}', 'en' => 'You are invited as a psychologist of {{provider}}']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\nAnda diundang sebagai psikolog mitra {{provider}}.\n\nBuka tautan ini untuk menerima undangan dan membuat kata sandi akun Anda:\n\n{{link}}\n\nSetelah itu Anda akan diminta memasang aplikasi autentikator saat masuk pertama kali.\n\nUndangan berlaku selama {{days}} hari dan hanya dapat digunakan sekali.\n\nJika Anda tidak mengenal pengirimnya, abaikan email ini.",
                    'en' => "Hello,\n\nYou are invited as a psychologist of {{provider}}.\n\nOpen this link to accept the invitation and set the password of your account:\n\n{{link}}\n\nAt your first sign-in you will be asked to set up an authenticator app.\n\nThe invitation is valid for {{days}} days and can be used once.\n\nIf you do not know the sender, you can ignore this email.",
                ]),
            ),
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function testDefinitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The candidate\'s name', 'Siti'),
            new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
            new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
            new EmailVariable('test_title', 'The title of the test', 'Logika dan angka'),
            new EmailVariable('due_at', 'When the test is due (Jakarta time)', '2026-10-14 17:00 WIB'),
            new EmailVariable('location', 'Where an onsite test is taken, as a line with its label; empty for an online test', 'Lokasi: Kantor Pusat, Lantai 3'),
            new EmailVariable('note', 'The company\'s note for the candidate, on its own lines with its label; empty when there is none', "\n\nCatatan: Harap membawa KTP."),
        ];

        return [
            new EmailTemplateDefinition(
                self::TEST_INVITED,
                'Sent to a candidate when a company sends them a test for an application.',
                $variables,
                TranslatedText::fromArray(['id' => 'Tes untuk lamaran {{job_title}}', 'en' => 'A test for your application for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} mengundang Anda mengerjakan tes \"{{test_title}}\" untuk lamaran posisi {{job_title}}.\n\nBatas pengerjaan: {{due_at}}\n\n{{location}}{{note}}\n\nBuka akun Anda untuk membaca petunjuk dan memulai tes.",
                    'en' => "Hello {{name}},\n\n{{company_name}} invited you to take the test \"{{test_title}}\" for your application for {{job_title}}.\n\nDue: {{due_at}}\n\n{{location}}{{note}}\n\nOpen your account to read the instructions and start the test.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::TEST_REMINDER,
                'Sent to a candidate about a day before the due time of a test they have not started.',
                $variables,
                TranslatedText::fromArray(['id' => 'Pengingat: tes untuk {{job_title}}', 'en' => 'Reminder: your test for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nIni pengingat bahwa tes \"{{test_title}}\" untuk lamaran posisi {{job_title}} di {{company_name}} belum Anda mulai.\n\nBatas pengerjaan: {{due_at}}\n\n{{location}}{{note}}\n\nBuka akun Anda untuk memulai tes.",
                    'en' => "Hello {{name}},\n\nA reminder that you have not started the test \"{{test_title}}\" for your application for {{job_title}} at {{company_name}}.\n\nDue: {{due_at}}\n\n{{location}}{{note}}\n\nOpen your account to start the test.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::TEST_EXPIRED,
                'Sent to a candidate when the due time of a test passed before they started it.',
                $variables,
                TranslatedText::fromArray(['id' => 'Tes untuk {{job_title}} telah berakhir', 'en' => 'Your test for {{job_title}} has expired']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nBatas pengerjaan tes \"{{test_title}}\" untuk lamaran posisi {{job_title}} di {{company_name}} telah lewat ({{due_at}}) dan tes tidak sempat dimulai.\n\nHubungi perusahaan jika Anda masih ingin mengerjakannya.",
                    'en' => "Hello {{name}},\n\nThe due time of the test \"{{test_title}}\" for your application for {{job_title}} at {{company_name}} has passed ({{due_at}}) and the test was not started.\n\nContact the company if you still want to take it.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::TEST_CANCELLED,
                'Sent to a candidate when a company cancels a test they were sent.',
                $variables,
                TranslatedText::fromArray(['id' => 'Tes untuk {{job_title}} dibatalkan', 'en' => 'Your test for {{job_title}} was cancelled']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} membatalkan tes \"{{test_title}}\" untuk lamaran posisi {{job_title}}.\n\nBuka akun Anda untuk melihat statusnya.",
                    'en' => "Hello {{name}},\n\n{{company_name}} cancelled the test \"{{test_title}}\" for your application for {{job_title}}.\n\nOpen your account to see its status.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::EXPLANATION_READY,
                'Sent to a candidate when a psychologist signed the report of a psychological test they took, so they can read the explanation of the result.',
                $variables,
                TranslatedText::fromArray(['id' => 'Penjelasan hasil tes Anda sudah siap', 'en' => 'The explanation of your test result is ready']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPsikolog telah menandatangani laporan tes \"{{test_title}}\" untuk lamaran posisi {{job_title}} di {{company_name}}.\n\nBuka akun Anda untuk membaca penjelasan hasil tes Anda.",
                    'en' => "Hello {{name}},\n\nA psychologist signed the report of the test \"{{test_title}}\" for your application for {{job_title}} at {{company_name}}.\n\nOpen your account to read the explanation of your result.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::EXPLANATION_REVISED,
                'Sent to a candidate when a psychologist revised the report of a psychological test they took, so they can read the revised explanation of the result.',
                $variables,
                TranslatedText::fromArray(['id' => 'Penjelasan hasil tes Anda telah direvisi', 'en' => 'The explanation of your test result was revised']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPsikolog merevisi laporan tes \"{{test_title}}\" untuk lamaran posisi {{job_title}} di {{company_name}}.\n\nBuka akun Anda untuk membaca penjelasan hasil tes yang sudah direvisi.",
                    'en' => "Hello {{name}},\n\nA psychologist revised the report of the test \"{{test_title}}\" for your application for {{job_title}} at {{company_name}}.\n\nOpen your account to read the revised explanation of your result.",
                ]),
            ),
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function teamDefinitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::TEAM_TEST_SUBMITTED,
                'Sent to an assessor when a test they score was submitted, when they switched this email on. It holds no candidate name and no answers.',
                [
                    new EmailVariable('name', 'The assessor\'s name', 'Budi'),
                    new EmailVariable('job_title', 'The title of the job', 'Backend Developer'),
                    new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('test_title', 'The title of the test', 'Tes logika'),
                ],
                TranslatedText::fromArray(['id' => 'Tes {{test_title}} menunggu penilaian Anda', 'en' => 'The test {{test_title}} is waiting for your scoring']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nSeorang pelamar {{job_title}} ({{company_name}}) sudah mengirim tes **{{test_title}}**. Buka daftar tes untuk menilai jawabannya.",
                    'en' => "Hello {{name}},\n\nA candidate for {{job_title}} ({{company_name}}) submitted the test **{{test_title}}**. Open your tests to score the answers.",
                ]),
            ),
        ];
    }
}
