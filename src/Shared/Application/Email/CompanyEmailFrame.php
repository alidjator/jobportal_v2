<?php

namespace Src\Shared\Application\Email;

use Src\Shared\Application\Company\PublicCompanies;

/**
 * What surrounds the text of an email a company sends through the platform (an interview, a test, an offer): the company's logo above the text (its name when it
 * has no public logo) and the fixed line that the platform and its companies never charge for recruitment. The platform decides these, never the company, so a
 * company cannot dress an email as something else.
 */
final readonly class CompanyEmailFrame
{
    private const array NOTICES = [
        'id' => '%s dan perusahaan tidak pernah memungut biaya dalam proses rekrutmen.',
        'en' => '%s and the companies on it never charge a fee in a recruitment process.',
    ];

    public function __construct(private PublicCompanies $companies, private string $platformName) {}

    /** @return array{name: string, logo_url: ?string, notice: string} */
    public function for(int $companyId, string $companyName, string $language): array
    {
        $card = $this->companies->card($companyId);

        return [
            'name' => $card->name ?? $companyName,
            'logo_url' => $card?->logoUrl,
            'notice' => sprintf(self::NOTICES[$language] ?? self::NOTICES['id'], $this->platformName),
        ];
    }
}
