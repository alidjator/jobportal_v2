<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuspendMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['message' => ['nullable', 'string', 'max:500']];
    }

    public function message(): ?string
    {
        return $this->filled('message') ? $this->string('message')->toString() : null;
    }
}
