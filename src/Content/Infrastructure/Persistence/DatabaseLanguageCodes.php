<?php

namespace Src\Content\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Content\Domain\LanguageCodes;

final class DatabaseLanguageCodes implements LanguageCodes
{
    public function exists(string $code): bool
    {
        // Tabel `languages` milik ReferenceData; dibaca read-only.
        return DB::table('languages')->where('code', $code)->exists();
    }
}
