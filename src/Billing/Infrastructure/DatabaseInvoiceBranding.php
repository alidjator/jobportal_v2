<?php

namespace Src\Billing\Infrastructure;

use Illuminate\Support\Facades\DB;
use Src\Billing\Application\Contracts\InvoiceBranding;

/**
 * Logo dari tabel `settings` (dibaca read-only; tabel dikelola admin). Logo di-embed sebagai data URI
 * agar dompdf tidak perlu akses jaringan; null bila file tidak ada atau bukan gambar raster/svg.
 */
final class DatabaseInvoiceBranding implements InvoiceBranding
{
    private const DEFAULT_LOGO = 'frontend/assets/images/logo/logo.svg';

    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];

    public function logo(): ?string
    {
        $path = DB::table('settings')->value('dark_logo') ?: self::DEFAULT_LOGO;
        $relative = ltrim((string) $path, '/');
        $file = realpath(public_path($relative));
        $public = realpath(public_path());

        if ($file === false || $public === false || ! str_starts_with($file, $public.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return null;
        }

        $mime = self::MIME[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;

        return $mime === null ? null : 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }

    public function appName(): string
    {
        return (string) config('app.name');
    }
}
