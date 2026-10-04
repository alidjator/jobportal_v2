<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Content\Domain\LegalDocumentType;

/** Create (with `type`) or update a draft. A draft may be incomplete; publishing requires every language. */
final class LegalDocumentRequest extends FormRequest
{
    private const int MAX_BODY_LENGTH = 200_000;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'array'],
            'title.id' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'array'],
            'body.id' => ['nullable', 'string', 'max:'.self::MAX_BODY_LENGTH],
            'body.en' => ['nullable', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ];

        if ($this->isMethod('POST')) {
            $rules['type'] = ['required', 'string', Rule::enum(LegalDocumentType::class)];
        }

        return $rules;
    }

    public function type(): LegalDocumentType
    {
        return LegalDocumentType::from($this->string('type')->toString());
    }

    /** @return array<array-key, mixed> */
    public function title(): array
    {
        return $this->array('title');
    }

    /** @return array<array-key, mixed> */
    public function body(): array
    {
        return $this->array('body');
    }
}
