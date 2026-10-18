<?php

namespace Src\Platform\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddEmailSuppressionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function details(): ?string
    {
        return $this->filled('details') ? trim($this->string('details')->toString()) : null;
    }
}
