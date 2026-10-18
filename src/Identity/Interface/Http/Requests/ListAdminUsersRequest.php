<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** `q` is part of the name or the email. Newest account first; `page` and `per_page` as in every paginated list. */
final class ListAdminUsersRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...$this->paginationRules(), 'q' => ['nullable', 'string', 'max:255']];
    }

    public function search(): ?string
    {
        return $this->filled('q') ? $this->string('q')->toString() : null;
    }
}
