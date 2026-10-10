<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Application\TwoFactor\CompleteAdminSignInCommand;

final class TwoFactorSignInRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'api';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:32'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toCommand(): CompleteAdminSignInCommand
    {
        return new CompleteAdminSignInCommand(
            $this->string('challenge_token')->toString(),
            $this->filled('code') ? $this->string('code')->toString() : null,
            $this->filled('recovery_code') ? $this->string('recovery_code')->toString() : null,
            $this->string('device_name', self::DEFAULT_DEVICE_NAME)->toString() ?: self::DEFAULT_DEVICE_NAME,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
