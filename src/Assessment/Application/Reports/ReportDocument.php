<?php

namespace Src\Assessment\Application\Reports;

use DateTimeImmutable;

/** Everything the PDF of a signed report prints. `autoIssued` (a report issued automatically and not revised since) adds the notice that it was issued automatically under the signer's responsibility. */
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
    ) {}
}
