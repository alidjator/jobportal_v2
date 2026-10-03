<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengganti trait Astrotomic\Translatable (tidak dipakai di aplikasi baru): memetakan tabel
 * `<x>` + `<x>_translations`, menyisipkan `name` (locale aktif -> fallback) dan relasi `translations`
 * agar bentuk JSON sama dengan aplikasi lama.
 */
abstract class TranslatableRecord extends Model
{
    protected $guarded = [];

    protected $with = ['translations'];

    abstract protected function translationTable(): string;

    abstract protected function translationKey(): string;

    public function translations(): HasMany
    {
        $related = (new TranslationRecord)->setTable($this->translationTable());

        return $this->newHasMany($related->newQuery(), $this, $related->qualifyColumn($this->translationKey()), 'id');
    }

    public function attributesToArray(): array
    {
        $array = parent::attributesToArray();
        $array['name'] = $this->translatedName();

        return $array;
    }

    private function translatedName(): ?string
    {
        if (! $this->relationLoaded('translations')) {
            return null;
        }

        $byLocale = $this->getRelation('translations')->keyBy('locale');
        $row = $byLocale[app()->getLocale()] ?? $byLocale[config('app.fallback_locale')] ?? null;

        return $row?->name;
    }
}
