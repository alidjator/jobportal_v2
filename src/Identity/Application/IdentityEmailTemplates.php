<?php

namespace Src\Identity\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Identity context, registered in the shared catalog by its service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class IdentityEmailTemplates
{
    public const string EMAIL_VERIFICATION = 'identity.email_verification';

    public const string PASSWORD_RESET = 'identity.password_reset';

    public const string TWO_FACTOR_REQUIRED = 'identity.two_factor_required';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The recipient\'s name', 'Siti'),
            new EmailVariable('code', 'The six-digit code', '049213', required: true),
            new EmailVariable('minutes', 'How many minutes the code stays valid', '15'),
        ];

        return [
            new EmailTemplateDefinition(
                self::EMAIL_VERIFICATION,
                'Sent to a new account with the code that verifies its email address.',
                $variables,
                TranslatedText::fromArray(['id' => 'Kode verifikasi Anda', 'en' => 'Your verification code']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nGunakan kode ini untuk memverifikasi alamat email Anda:\n\n# {{code}}\n\nKode berlaku selama {{minutes}} menit dan hanya dapat digunakan sekali.\n\nJika Anda tidak membuat akun, abaikan email ini.",
                    'en' => "Hello {{name}},\n\nUse this code to verify your email address:\n\n# {{code}}\n\nThe code is valid for {{minutes}} minutes and can be used once.\n\nIf you did not create an account, you can ignore this email.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::PASSWORD_RESET,
                'Sent when someone asks to reset a password, with the code that allows it.',
                $variables,
                TranslatedText::fromArray(['id' => 'Kode reset kata sandi Anda', 'en' => 'Your password reset code']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nGunakan kode ini untuk menetapkan kata sandi baru:\n\n# {{code}}\n\nKode berlaku selama {{minutes}} menit dan hanya dapat digunakan sekali.\n\nJika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tidak berubah.",
                    'en' => "Hello {{name}},\n\nUse this code to set a new password:\n\n# {{code}}\n\nThe code is valid for {{minutes}} minutes and can be used once.\n\nIf you did not ask to reset your password, you can ignore this email. Your password stays unchanged.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::TWO_FACTOR_REQUIRED,
                'Sent to a company member when two-factor sign-in became required for their account (they were promoted to company admin, or their company switched it on) and they have not set it up yet.',
                [new EmailVariable('name', 'The recipient\'s name', 'Budi')],
                TranslatedText::fromArray(['id' => 'Verifikasi dua langkah kini diwajibkan', 'en' => 'Two-step verification is now required']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nUntuk menjaga keamanan data pelamar, akun Anda kini wajib memakai verifikasi dua langkah. Sesi Anda yang sedang aktif telah diakhiri.\n\nSaat Anda masuk berikutnya, Anda akan diminta memasang aplikasi autentikator (misalnya Google Authenticator) di ponsel Anda. Siapkan ponsel Anda sebelum masuk.",
                    'en' => "Hello {{name}},\n\nTo keep applicant data safe, your account now needs two-step verification. Your active sessions have ended.\n\nThe next time you sign in you will be asked to set up an authenticator app (for example Google Authenticator) on your phone. Have your phone ready before you sign in.",
                ]),
            ),
        ];
    }
}
