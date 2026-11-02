<?php

namespace Src\Assessment\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Src\Assessment\Application\Reports\ReportVerificationLinks;

/** The address in `app.report_verification_url` (env `REPORT_VERIFICATION_URL`, for example `https://karir.satu.solutions/verifikasi-laporan`) with the number as `?nomor=`. */
final readonly class ConfiguredReportVerificationLinks implements ReportVerificationLinks
{
    public function __construct(private Repository $config) {}

    public function forNumber(string $number): ?string
    {
        $base = $this->config->get('app.report_verification_url');

        if (! is_string($base) || trim($base) === '') {
            return null;
        }

        return rtrim($base, '?&').(str_contains($base, '?') ? '&' : '?').'nomor='.rawurlencode(preg_replace('/-R\d+$/', '', $number) ?? $number);
    }
}
