<?php

namespace Src\Company\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Application\Activity\CompanyActivityFilter;
use Src\Company\Application\Activity\CompanyActivityGroups;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/**
 * The company activity log and its CSV export take the same filters: `filter[user_id]`, `filter[group]` (`sign_in`, `security`, `data_access`, `export`,
 * `team`, `jobs`, `settings`), `filter[from]` and `filter[to]` (ISO 8601 times, both inclusive); the list adds `cursor` and `per_page`. Any other filter is refused with 422.
 */
final class ListCompanyActivityRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:user_id,group,from,to'],
            'filter.user_id' => ['nullable', 'integer', 'min:1'],
            'filter.group' => ['nullable', 'string', Rule::in(CompanyActivityGroups::names())],
            'filter.from' => ['nullable', 'date'],
            'filter.to' => ['nullable', 'date', 'after_or_equal:filter.from'],
        ];
    }

    public function filters(): CompanyActivityFilter
    {
        return new CompanyActivityFilter(
            $this->filled('filter.user_id') ? $this->integer('filter.user_id') : null,
            $this->filled('filter.group') ? $this->string('filter.group')->toString() : null,
            $this->time('filter.from'),
            $this->time('filter.to'),
        );
    }

    private function time(string $key): ?DateTimeImmutable
    {
        return $this->filled($key) ? (new DateTimeImmutable($this->string($key)->toString()))->setTimezone(new DateTimeZone('UTC')) : null;
    }
}
