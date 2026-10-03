<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Menyusun bentuk JSON model `Translatable` lama (atribut + `name` + `translations`). */
final class TranslatableRows
{
    public static function iso(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toJSON() : null;
    }

    /**
     * @param  list<object>  $rows  baris tabel utama (wajib punya `id`)
     * @return array<int, array<string, mixed>> id => baris + name + translations
     */
    public static function decorate(array $rows, string $translationTable, string $foreignKey): array
    {
        $ids = array_map(fn ($r) => $r->id, $rows);
        $translations = $ids === []
            ? collect()
            : DB::table($translationTable)->whereIn($foreignKey, $ids)->orderBy('id')->get()->groupBy($foreignKey);

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');
        $result = [];

        foreach ($rows as $row) {
            $own = $translations[$row->id] ?? collect();
            $byLocale = $own->keyBy('locale');
            $name = ($byLocale[$locale] ?? $byLocale[$fallback] ?? null)?->name;

            $item = (array) $row;
            foreach (['created_at', 'updated_at'] as $date) {
                if (array_key_exists($date, $item)) {
                    $item[$date] = self::iso($item[$date]);
                }
            }
            $item['name'] = $name;
            $item['translations'] = $own->map(function ($t) {
                $t = (array) $t;
                $t['created_at'] = self::iso($t['created_at'] ?? null);
                $t['updated_at'] = self::iso($t['updated_at'] ?? null);

                return $t;
            })->values()->all();

            $result[$row->id] = $item;
        }

        return $result;
    }
}
