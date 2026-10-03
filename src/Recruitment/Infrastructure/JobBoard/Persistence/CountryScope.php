<?php

namespace Src\Recruitment\Infrastructure\JobBoard\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\RequestContext;

/**
 * Membatasi lowongan berdasarkan negara: header `selected_country` (ID negara, lewat RequestContext)
 * atau, bila mode aplikasi `single_base`, negara tunggal dari pengaturan.
 */
final class CountryScope
{
    public function __construct(
        private readonly RequestContext $context,
        private readonly JobBoardSettings $settings,
    ) {}

    /** @param  bool  $singleBaseFirst  true: negara `single_base` menang atas header (perilaku related jobs/popular) */
    public function apply(Builder $query, string $column = 'jobs.country', bool $singleBaseFirst = false): Builder
    {
        $name = $this->countryName($singleBaseFirst);

        if ($name !== null) {
            $query->where($column, 'LIKE', '%'.$name.'%');
        }

        return $query;
    }

    public function countryName(bool $singleBaseFirst = false): ?string
    {
        $selected = $this->selectedName();
        $single = $this->settings->singleBaseCountryName();

        return $singleBaseFirst ? ($single ?? $selected) : ($selected ?? $single);
    }

    private function selectedName(): ?string
    {
        if ($this->context->countryId === null) {
            return null;
        }

        return DB::table('countries')->where('id', $this->context->countryId)->value('name');
    }
}
