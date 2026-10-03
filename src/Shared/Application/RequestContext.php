<?php

namespace Src\Shared\Application;

/**
 * Preferensi per-request (bahasa, negara, mata uang).
 *
 * Aplikasi lama menyimpannya di session; API ini stateless sehingga nilainya
 * dikirim klien lewat header: `X-Language` / `Accept-Language`,
 * `selected_country` / `X-Country`, dan `X-Currency`.
 */
final class RequestContext
{
    public function __construct(
        public readonly ?string $language = null,
        public readonly ?int $countryId = null,
        public readonly ?string $currency = null,
    ) {}
}
