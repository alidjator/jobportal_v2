<?php

namespace Src\JobSeeker\Application\Export;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email template of the personal data export, registered in the shared catalog by the JobSeeker service provider. */
final class DataExportEmailTemplates
{
    public const string READY = 'account.data_export_ready';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::READY,
                'Sent when the copy of a job seeker\'s personal data they asked for is ready to download. Always sent: it answers a request. The copy carries no link here, the job seeker downloads it while signed in.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Siti'),
                    new EmailVariable('expires_at', 'When the copy is deleted from the server (Jakarta time)', '16 Oktober 2026 10:00 WIB'),
                ],
                TranslatedText::fromArray(['id' => 'Salinan data pribadi Anda sudah siap', 'en' => 'The copy of your personal data is ready']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nSalinan data pribadi yang Anda minta sudah siap. Masuk ke akun Anda dan unduh dari pengaturan akun sebelum {{expires_at}}; setelah itu salinan dihapus dari server kami.\n\nJika Anda tidak merasa memintanya, segera ubah kata sandi Anda.",
                    'en' => "Hello {{name}},\n\nThe copy of your personal data that you asked for is ready. Sign in to your account and download it from your account settings before {{expires_at}}; after that it is deleted from our servers.\n\nIf you did not ask for it, change your password at once.",
                ]),
            ),
        ];
    }
}
