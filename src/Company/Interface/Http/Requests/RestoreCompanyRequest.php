<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RestoreCompanyRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:1000']];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? trim($this->string('note')->toString()) : null;
    }
}
