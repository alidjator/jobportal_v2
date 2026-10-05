<?php

namespace Src\Company\Application\Images;

/** What can be told from the storage path of a company picture (`company/logos/<random>.png`). */
final class CompanyImageFile
{
    /** The random part of the file name: it changes whenever the picture is replaced, so URLs that carry it as `?v=` never serve a stale picture. */
    public static function version(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function mimeType(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
    }
}
