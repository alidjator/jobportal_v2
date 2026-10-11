<?php

namespace Src\Assessment\Application\Reports;

use DateTimeImmutable;

/** Everything the PDF of a signed report prints. */
final readonly class ReportDocument
{
    /** @param  list<array{scale: string, raw: float, standard: float|null, band: string|null}>  $scales */
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
    ) {}
}
