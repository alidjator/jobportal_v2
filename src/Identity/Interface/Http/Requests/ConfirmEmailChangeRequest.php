<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The code sent to the new address, plus the password; an account without a password sends `old_code` (the code sent to its current address) instead. */
final class ConfirmEmailChangeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => ['nullable', 'string', 'max:255'],
            'old_code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    public function password(): ?string
    {
        return $this->filled('password') ? $this->string('password')->toString() : null;
    }

    public function oldCode(): ?string
    {
        return $this->filled('old_code') ? $this->string('old_code')->toString() : null;
    }
}
