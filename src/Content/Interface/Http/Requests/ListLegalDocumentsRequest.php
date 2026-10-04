<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Content\Domain\LegalDocumentType;

/** Allowed filter: `filter[type]`. Any other filter key is refused with 422. */
final class ListLegalDocumentsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:type'],
            'filter.type' => ['nullable', 'string', Rule::enum(LegalDocumentType::class)],
        ];
    }

    public function type(): ?LegalDocumentType
    {
        $type = $this->input('filter.type');

        return is_string($type) ? LegalDocumentType::tryFrom($type) : null;
    }
}
