<?php

namespace Src\Assessment\Application\Reports;

use DateTimeImmutable;

/** Everything the PDF of a signed report prints. `recommendation` is the Indonesian label of the hiring advice. `autoIssued` (a report issued automatically and not revised since) adds the notice that it was issued automatically under the signer's responsibility. `verificationUrl` is the public address where the number can be checked (printed in the footer). */
final readonly class ReportDocument
{
    /** @param  list<array{scale: string, raw: float, standard: float|null, band: string|null, norm_group: array{age_min: int|null, age_max: int|null, education_rank_min: int|null, education_rank_max: int|null}|null}>  $scales */
    public function __construct(
        public string $providerName,
        public string $providerLegalName,
        public string $reportNumber,
        public string $testTitle,
        public string $candidateName,
        public string $body,
        public array $scales,
        public string $signerName,
        public string $signerStrNumber,
        public string $signerSilpNumber,
        public DateTimeImmutable $signedAt,
        public bool $autoIssued = false,
        public ?string $recommendation = null,
        public ?string $verificationUrl = null,
        /** @var array{name: string, legal_name: string, address: string|null, practice_licence_number: string|null, phone: string|null, email: string|null, logo: string|null}|null the institution as it was when this version was signed (`logo` is a data address), drawn where `{{provider_letterhead}}` stands */
        public ?array $letterhead = null,
        /** The signer's signature image as a data address, drawn where `{{signature_image}}` stands. */
        public ?string $signatureImage = null,
    ) {}

    public const string LETTERHEAD = '{{provider_letterhead}}';

    public const string SIGNATURE = '{{signature_image}}';

    /**
     * The text of the report cut at the two picture placeholders, in order: `text` parts to print as they are and `letterhead` and `signature` parts to draw. A picture the version does not have is left out.
     *
     * @return list<array{type: string, text: string}>
     */
    public function bodyParts(): array
    {
        $parts = [];

        foreach (preg_split('/(\{\{provider_letterhead\}\}|\{\{signature_image\}\})/', $this->body, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            if ($piece === self::LETTERHEAD) {
                $parts[] = ['type' => 'letterhead', 'text' => ''];
            } elseif ($piece === self::SIGNATURE) {
                $parts[] = ['type' => 'signature', 'text' => ''];
            } else {
                $parts[] = ['type' => 'text', 'text' => $piece];
            }
        }

        return $parts;
    }

    /** Whether the text places the institution's letterhead itself (the default header is then left out). */
    public function hasLetterheadPlaceholder(): bool
    {
        return str_contains($this->body, self::LETTERHEAD);
    }
}
