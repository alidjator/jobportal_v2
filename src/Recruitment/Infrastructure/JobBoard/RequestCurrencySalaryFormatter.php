<?php

namespace Src\Recruitment\Infrastructure\JobBoard;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobBoard\SalaryFormatter;
use Src\Shared\Application\RequestContext;

/**
 * Format gaji singkat (helper lama `currencyAmountShort` + `currentCurrencyCode`).
 * Mata uang aktif berasal dari header `X-Currency` (RequestContext) menggantikan session;
 * kurs = rate(mata uang dasar) / rate(mata uang aktif) dari tabel `currencies`.
 */
final class RequestCurrencySalaryFormatter implements SalaryFormatter
{
    private ?float $rate = null;

    private ?string $code = null;

    public function __construct(private readonly RequestContext $context) {}

    public function label(?string $mode, mixed $min, mixed $max, ?string $custom): ?string
    {
        if ($mode !== 'range') {
            return $custom;
        }

        return $this->short($min).' - '.$this->short($max).' '.$this->code();
    }

    private function short(mixed $amount): string
    {
        $num = (float) $amount * $this->rate();
        $units = ['', 'K', 'M', 'B', 'T'];

        for ($i = 0; $num >= 1000 && $i < count($units) - 1; $i++) {
            $num /= 1000;
        }

        return round($num, 0).$units[$i];
    }

    private function code(): string
    {
        $this->resolve();

        return (string) $this->code;
    }

    private function rate(): float
    {
        $this->resolve();

        return (float) $this->rate;
    }

    private function resolve(): void
    {
        if ($this->rate !== null) {
            return;
        }

        $base = (string) config('jobportal.default_currency');
        $this->code = $base;
        $this->rate = 1.0;

        if ($this->context->currency === null || strcasecmp($this->context->currency, $base) === 0) {
            return;
        }

        $current = DB::table('currencies')->where('code', $this->context->currency)->first(['code', 'rate']);
        $baseRate = DB::table('currencies')->where('code', $base)->value('rate');

        if ($current && $baseRate && (float) $current->rate > 0) {
            $this->code = $current->code;
            $this->rate = (float) $baseRate / (float) $current->rate;
        }
    }
}
