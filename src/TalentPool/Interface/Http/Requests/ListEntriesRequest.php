<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;
use Src\TalentPool\Application\EntryFilter;
use Src\TalentPool\Application\EntrySort;
use Src\TalentPool\Domain\EntrySource;

/** Allowed: `filter[category_id]`, `filter[source]` (`search` or `application`), `filter[q]`, `sort` (`saved_at`, the default, or `last_activity`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListEntriesRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:category_id,source,q'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'filter.source' => ['nullable', 'string', Rule::enum(EntrySource::class)],
            'filter.q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::enum(EntrySort::class)],
        ];
    }

    public function toFilter(): EntryFilter
    {
        $query = $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : '';

        return new EntryFilter(
            $this->filled('filter.category_id') ? $this->integer('filter.category_id') : null,
            $this->filled('filter.source') ? EntrySource::from($this->string('filter.source')->toString()) : null,
            $query === '' ? null : $query,
            $this->filled('sort') ? EntrySort::from($this->string('sort')->toString()) : EntrySort::SavedAt,
        );
    }
}
