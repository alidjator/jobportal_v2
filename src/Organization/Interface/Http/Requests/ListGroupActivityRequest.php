<?php

namespace Src\Organization\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Src\Organization\Application\Activity\GroupActivityFilter;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/**
 * The group activity log takes `filter[user_id]`, `filter[company_id]` (a tenant of the group), `filter[action]` (one audit action), `filter[from]` and `filter[to]`
 * (ISO 8601 times, both inclusive), plus `cursor` and `per_page`. Any other filter is refused with 422.
 */
final class ListGroupActivityRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:user_id,company_id,action,from,to'],
            'filter.user_id' => ['nullable', 'integer', 'min:1'],
            'filter.company_id' => ['nullable', 'integer', 'min:1'],
            'filter.action' => ['nullable', 'string', 'max:64'],
            'filter.from' => ['nullable', 'date'],
            'filter.to' => ['nullable', 'date', 'after_or_equal:filter.from'],
        ];
    }

    public function filters(): GroupActivityFilter
    {
        return new GroupActivityFilter(
            $this->filled('filter.user_id') ? $this->integer('filter.user_id') : null,
            $this->filled('filter.company_id') ? $this->integer('filter.company_id') : null,
            $this->filled('filter.action') ? $this->string('filter.action')->toString() : null,
            $this->time('filter.from'),
            $this->time('filter.to'),
        );
    }

    private function time(string $key): ?DateTimeImmutable
    {
        return $this->filled($key) ? (new DateTimeImmutable($this->string($key)->toString()))->setTimezone(new DateTimeZone('UTC')) : null;
    }
}
