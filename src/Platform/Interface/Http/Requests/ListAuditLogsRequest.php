<?php

namespace Src\Platform\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditLogQuery;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/**
 * Allowed filters: action, actor_type, actor_id, subject_type, subject_id, from and to (ISO 8601 times of the event, both inclusive).
 * Any other filter is refused with 422. The list is newest first and cursor-paginated (`cursor`, `per_page`).
 */
final class ListAuditLogsRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:action,actor_type,actor_id,subject_type,subject_id,from,to'],
            'filter.action' => ['nullable', 'string', 'max:128'],
            'filter.actor_type' => ['nullable', 'string', Rule::enum(AuditActorType::class)],
            'filter.actor_id' => ['nullable', 'integer', 'min:1'],
            'filter.subject_type' => ['nullable', 'string', 'max:64'],
            'filter.subject_id' => ['nullable', 'integer', 'min:1'],
            'filter.from' => ['nullable', 'date'],
            'filter.to' => ['nullable', 'date', 'after_or_equal:filter.from'],
        ];
    }

    public function toQuery(): AuditLogQuery
    {
        return new AuditLogQuery(
            $this->filled('filter.action') ? $this->string('filter.action')->toString() : null,
            $this->filled('filter.actor_type') ? AuditActorType::from($this->string('filter.actor_type')->toString()) : null,
            $this->filled('filter.actor_id') ? $this->integer('filter.actor_id') : null,
            $this->filled('filter.subject_type') ? $this->string('filter.subject_type')->toString() : null,
            $this->filled('filter.subject_id') ? $this->integer('filter.subject_id') : null,
            $this->time('filter.from'),
            $this->time('filter.to'),
        );
    }

    private function time(string $key): ?DateTimeImmutable
    {
        return $this->filled($key)
            ? (new DateTimeImmutable($this->string($key)->toString()))->setTimezone(new DateTimeZone('UTC'))
            : null;
    }
}
