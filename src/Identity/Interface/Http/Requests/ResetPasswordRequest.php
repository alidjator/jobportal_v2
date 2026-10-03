<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'code' => 'required',
            'email' => 'required|string|max:100|email|exists:users,email',
            'password' => 'required|min:8|max:50',
        ];
    }
}
