<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Application\Auth\SignInCommand;

final class SignInRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'api';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toCommand(): SignInCommand
    {
        return new SignInCommand(
            $this->string('email')->toString(),
            $this->string('password')->toString(),
            $this->string('device_name', self::DEFAULT_DEVICE_NAME)->toString() ?: self::DEFAULT_DEVICE_NAME,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
