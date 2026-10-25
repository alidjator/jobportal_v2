<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Allowed: `filter[q]` (title). The list is not paginated. Any other filter is refused with 422. */
final class ListSendableTestsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:q'],
            'filter.q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }
}
