<?php

namespace Src\Shared\Interface\Http\Requests;

use Src\Shared\Application\Pagination\PageRequest;

/** Validation and reading of `page` and `per_page` for a list request (FormRequest classes use this trait). */
trait PaginatedRequest
{
    /** @return array<string, list<string>> */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.PageRequest::MAX_PER_PAGE],
        ];
    }

    public function pageRequest(): PageRequest
    {
        return new PageRequest(
            $this->integer('page', 1),
            $this->integer('per_page', PageRequest::DEFAULT_PER_PAGE),
        );
    }
}
