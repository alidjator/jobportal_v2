<?php

namespace Src\Company\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Company context, registered in the shared catalog by its service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class CompanyEmailTemplates
{
    public const string INVITATION = 'company.invitation';

    public const string MEMBER_SUSPENDED = 'company.member_suspended';

    public const string MEMBER_REACTIVATED = 'company.member_reactivated';

    public const string OWNERSHIP_GAINED = 'company.ownership_gained';

    public const string OWNERSHIP_HANDED_OVER = 'company.ownership_handed_over';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::INVITATION,
                'Sent when someone is invited to join a company, with the link that accepts the invitation.',
                [
                    new EmailVariable('company', 'The name of the company that invites', 'PT Contoh Maju'),
                    new EmailVariable('role', 'The role offered, in the language of the email', 'Team member'),
                    new EmailVariable('link', 'The link that accepts the invitation', 'https://example.test/invitations/abc123', required: true),
                    new EmailVariable('days', 'How many days the invitation stays valid', '7'),
                ],
                TranslatedText::fromArray(['id' => 'Undangan bergabung dengan {{company}}', 'en' => 'You are invited to join {{company}}']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\n{{company}} mengundang Anda bergabung sebagai {{role}}.\n\nBuka tautan ini untuk menerima undangan dan membuat akun Anda:\n\n{{link}}\n\nUndangan berlaku selama {{days}} hari dan hanya dapat digunakan sekali.\n\nJika Anda tidak mengenal pengirimnya, abaikan email ini.",
                    'en' => "Hello,\n\n{{company}} invites you to join as {{role}}.\n\nOpen this link to accept the invitation and create your account:\n\n{{link}}\n\nThe invitation is valid for {{days}} days and can be used once.\n\nIf you do not know the sender, you can ignore this email.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::MEMBER_SUSPENDED,
                'Sent to a member whose access to the company a company admin suspended.',
                [
                    new EmailVariable('company', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('message', 'The message of the company admin, empty when there is none', 'Hubungi HR untuk informasi lebih lanjut.'),
                ],
                TranslatedText::fromArray(['id' => 'Akses Anda ke {{company}} ditangguhkan', 'en' => 'Your access to {{company}} was suspended']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\nAdmin {{company}} menangguhkan akses Anda untuk sementara. Anda tidak dapat masuk sampai akses Anda diaktifkan kembali; pekerjaan dan data Anda tetap tersimpan.\n\n{{message}}",
                    'en' => "Hello,\n\nAn admin of {{company}} suspended your access for now. You cannot sign in until it is restored; your jobs and data are kept.\n\n{{message}}",
                ]),
            ),
            new EmailTemplateDefinition(
                self::MEMBER_REACTIVATED,
                'Sent to a member whose access to the company a company admin restored.',
                [new EmailVariable('company', 'The name of the company', 'PT Contoh Maju')],
                TranslatedText::fromArray(['id' => 'Akses Anda ke {{company}} diaktifkan kembali', 'en' => 'Your access to {{company}} was restored']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\nAkses Anda ke {{company}} sudah diaktifkan kembali. Silakan masuk seperti biasa; izin dan pekerjaan Anda tidak berubah.",
                    'en' => "Hello,\n\nYour access to {{company}} was restored. Sign in as usual; your permissions and jobs are unchanged.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OWNERSHIP_GAINED,
                'Sent to the company admin who became the owner (primary account) of a company.',
                [
                    new EmailVariable('company', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('previous_owner', 'The name of the previous owner, empty when a platform admin moved the ownership', 'Budi'),
                ],
                TranslatedText::fromArray(['id' => 'Anda kini pemilik akun {{company}}', 'en' => 'You are now the owner of {{company}}']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\nAnda kini menjadi pemilik (akun utama) {{company}}. {{previous_owner}}\n\nPemilik tidak dapat ditangguhkan atau dihapus oleh admin lain.",
                    'en' => "Hello,\n\nYou are now the owner (primary account) of {{company}}. {{previous_owner}}\n\nThe owner cannot be suspended or removed by another admin.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OWNERSHIP_HANDED_OVER,
                'Sent to the former owner of a company when the ownership moved to another company admin.',
                [
                    new EmailVariable('company', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('new_owner', 'The name of the new owner', 'Sari'),
                ],
                TranslatedText::fromArray(['id' => 'Kepemilikan akun {{company}} dipindahkan', 'en' => 'The ownership of {{company}} was moved']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\nKepemilikan (akun utama) {{company}} dipindahkan ke {{new_owner}}. Anda tetap admin perusahaan.",
                    'en' => "Hello,\n\nThe ownership (primary account) of {{company}} moved to {{new_owner}}. You remain a company admin.",
                ]),
            ),
        ];
    }
}
