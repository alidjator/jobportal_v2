<?php

namespace Src\Notification\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/** Allowed filter: `read` (true or false). Any other filter is refused with 422. Newest first, cursor-paginated (`cursor`, `per_page`). */
final class ListNotificationsRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:read'],
            'filter.read' => ['nullable', 'in:true,false,1,0'],
        ];
    }

    /** Null when the list should hold read and unread notifications alike. */
    public function readFilter(): ?bool
    {
        return $this->filled('filter.read') ? $this->boolean('filter.read') : null;
    }
}
