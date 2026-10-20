<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** `q` is part of the name or the email; `filter[imported]` and `filter[signed_in]` (true or false) keep the accounts that came from karir.pkp or not, and that ever signed in or not. Newest account first; `page` and `per_page` as in every paginated list. */
final class ListAdminUsersRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...$this->paginationRules(), 'q' => ['nullable', 'string', 'max:255'], 'filter.imported' => ['nullable', 'in:true,false,1,0'], 'filter.signed_in' => ['nullable', 'in:true,false,1,0']];
    }

    public function search(): ?string
    {
        return $this->filled('q') ? $this->string('q')->toString() : null;
    }

    public function imported(): ?bool
    {
        return $this->filled('filter.imported') ? $this->boolean('filter.imported') : null;
    }

    public function signedIn(): ?bool
    {
        return $this->filled('filter.signed_in') ? $this->boolean('filter.signed_in') : null;
    }
}
