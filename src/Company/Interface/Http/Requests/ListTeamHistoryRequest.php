<?php

namespace Src\Company\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/** Allowed: `filter[action]`, `filter[user_id]` (the member concerned), `filter[from]` and `filter[to]` (ISO 8601 times, both inclusive), `cursor` and `per_page`. Any other filter is refused with 422. */
final class ListTeamHistoryRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:action,user_id,from,to'],
            'filter.action' => ['nullable', 'string', 'max:128'],
            'filter.user_id' => ['nullable', 'integer', 'min:1'],
            'filter.from' => ['nullable', 'date'],
            'filter.to' => ['nullable', 'date', 'after_or_equal:filter.from'],
        ];
    }

    public function action(): ?string
    {
        return $this->filled('filter.action') ? $this->string('filter.action')->toString() : null;
    }

    public function userId(): ?int
    {
        return $this->filled('filter.user_id') ? $this->integer('filter.user_id') : null;
    }

    public function from(): ?DateTimeImmutable
    {
        return $this->time('filter.from');
    }

    public function to(): ?DateTimeImmutable
    {
        return $this->time('filter.to');
    }

    private function time(string $key): ?DateTimeImmutable
    {
        return $this->filled($key) ? (new DateTimeImmutable($this->string($key)->toString()))->setTimezone(new DateTimeZone('UTC')) : null;
    }
}
