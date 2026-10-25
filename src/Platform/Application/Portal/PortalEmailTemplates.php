<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The career portal alert emails, registered in the shared catalog by the Platform service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class PortalEmailTemplates
{
    public const string COMPANY_MISSING = 'platform.portal_company_missing';

    public const string RECOVERED = 'platform.portal_recovered';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::COMPANY_MISSING,
                'Sent to the operations address when the career site has to skip a company its settings name (missing, suspended or hidden).',
                [new EmailVariable('companies', 'The skipped companies, one per line', "PT Example (suspended)  \n#12 (missing)", required: true)],
                TranslatedText::fromArray(['id' => 'Situs karier melewati perusahaan', 'en' => 'The career site skips a company']),
                TranslatedText::fromArray([
                    'id' => "Situs karier tetap tampil, tetapi perusahaan berikut dilewati karena tidak dapat ditampilkan:\n\n{{companies}}\n\nPeriksa pengaturan portal di Console, atau aktifkan kembali perusahaannya.",
                    'en' => "The career site stays up, but these companies are skipped because they cannot be shown:\n\n{{companies}}\n\nCheck the portal settings in the Console, or restore the companies.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::RECOVERED,
                'Sent to the operations address when the career site shows every company its settings name again.',
                [],
                TranslatedText::fromArray(['id' => 'Situs karier menampilkan semua perusahaan', 'en' => 'The career site shows every company again']),
                TranslatedText::fromArray([
                    'id' => 'Semua perusahaan di pengaturan portal dapat ditampilkan lagi.',
                    'en' => 'Every company in the portal settings can be shown again.',
                ]),
            ),
        ];
    }
}
