<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Rules\NewPassword;

final class ChangePasswordRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => [...NewPassword::rules(), 'confirmed', 'different:current_password'],
        ];
    }

    public function currentPassword(): string
    {
        return $this->string('current_password')->toString();
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }
}
