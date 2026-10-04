<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestAccountDeletionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
            'confirm' => ['required', 'accepted'],
        ];
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
