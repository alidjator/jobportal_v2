<?php

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

/**
 * Membaca kolom `name` dari tabel `*_translations` (skema Astrotomic/laravel-translatable
 * dari aplikasi lama): locale aktif → fallback locale → null.
 */
final class TranslatedNames
{
    /**
     * @param  iterable<int>  $ids
     * @return array<int, string|null> id => nama
     */
    public static function for(string $translationTable, string $foreignKey, iterable $ids, string $column = 'name'): array
    {
        $ids = collect($ids)->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $rows = DB::table($translationTable)
            ->whereIn($foreignKey, $ids)
            ->whereIn('locale', array_unique([$locale, $fallback]))
            ->get([$foreignKey, 'locale', $column])
            ->groupBy($foreignKey);

        $result = [];
        foreach ($ids as $id) {
            $byLocale = ($rows[$id] ?? collect())->keyBy('locale');
            $row = $byLocale[$locale] ?? $byLocale[$fallback] ?? null;
            $result[$id] = $row?->{$column};
        }

        return $result;
    }
}
