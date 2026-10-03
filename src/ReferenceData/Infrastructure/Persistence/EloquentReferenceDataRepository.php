<?php

namespace Src\ReferenceData\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\ReferenceData\Domain\LookupType;
use Src\ReferenceData\Domain\ReferenceDataRepository;
use Src\ReferenceData\Infrastructure\Persistence\Eloquent\Country;
use Src\ReferenceData\Infrastructure\Persistence\Eloquent\Currency;
use Src\ReferenceData\Infrastructure\Persistence\Eloquent\Language;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

class EloquentReferenceDataRepository implements ReferenceDataRepository
{
    public function lookups(LookupType $type): array
    {
        $ids = DB::table($type->table())->orderBy('id')->pluck('id');
        $names = TranslatedNames::for($type->translationTable(), $type->foreignKey(), $ids);

        return $ids->map(fn ($id) => ['id' => $id, 'name' => $names[$id] ?? null])->all();
    }

    public function jobCategories(): array
    {
        $rows = DB::table('job_categories')->orderBy('id')->get(['id', 'image']);
        $names = TranslatedNames::for('job_category_translations', 'job_category_id', $rows->pluck('id'));

        return $rows->map(fn ($row) => [
            'id' => $row->id,
            'name' => $names[$row->id] ?? null,
            'image' => asset($row->image ?: 'backend/image/default.png'),
            'is_svg' => str_contains((string) $row->image, '.svg'),
        ])->all();
    }

    public function popularTags(int $limit): array
    {
        $rows = DB::table('tags')
            ->leftJoin('job_tag', 'job_tag.tag_id', '=', 'tags.id')
            ->where('tags.show_popular_list', 1)
            ->groupBy('tags.id')
            ->orderByDesc(DB::raw('count(job_tag.id)'))
            ->orderByDesc('tags.id')
            ->limit($limit)
            ->get(['tags.id', DB::raw('count(job_tag.id) as tags_count')]);

        $names = TranslatedNames::for('tag_translations', 'tag_id', $rows->pluck('id'));

        return $rows->map(fn ($row) => [
            'id' => $row->id,
            'name' => $names[$row->id] ?? null,
            'slug' => null,
            'jobs_count' => (int) $row->tags_count,
            'tags_count' => (int) $row->tags_count,
        ])->all();
    }

    public function countries(): array
    {
        return Country::query()->orderBy('id')->get(['id', 'name', 'sortname', 'status'])->toArray();
    }

    public function country(int $id): ?array
    {
        return Country::query()->find($id)?->toArray();
    }

    public function firstCountry(): ?array
    {
        return Country::query()->orderBy('id')->first()?->toArray();
    }

    public function currencies(): array
    {
        return Currency::query()->orderBy('id')->get(['id', 'name', 'code', 'symbol'])->toArray();
    }

    public function currency(string $code): ?array
    {
        return Currency::query()->where('code', $code)->first()?->toArray();
    }

    public function languages(): array
    {
        return Language::query()->orderBy('id')->get()->toArray();
    }

    public function language(string $code): ?array
    {
        return Language::query()->where('code', $code)->first()?->toArray();
    }
}
