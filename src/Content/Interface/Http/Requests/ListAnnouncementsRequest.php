<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

final class ListAnnouncementsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
