<?php

namespace Src\Shared\Interface\Http\Requests;

use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Application\Pagination\PageRequest;

/** Validation and reading of `cursor` and `per_page` for a cursor-paginated list request (FormRequest classes use this trait). */
trait CursorPaginatedRequest
{
    /** @return array<string, list<string>> */
    protected function cursorRules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:512'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.PageRequest::MAX_PER_PAGE],
        ];
    }

    public function cursorRequest(): CursorRequest
    {
        return new CursorRequest(
            $this->filled('cursor') ? $this->string('cursor')->toString() : null,
            $this->integer('per_page', PageRequest::DEFAULT_PER_PAGE),
        );
    }
}
