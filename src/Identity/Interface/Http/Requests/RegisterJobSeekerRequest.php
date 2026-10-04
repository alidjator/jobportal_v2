<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Application\Auth\RegisterJobSeekerCommand;
use Src\Shared\Application\RequestContext;

final class RegisterJobSeekerRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'api';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'campaign_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ];
    }

    public function toCommand(RequestContext $context): RegisterJobSeekerCommand
    {
        return new RegisterJobSeekerCommand(
            trim($this->string('name')->toString()),
            $this->string('email')->toString(),
            $this->string('password')->toString(),
            $context->language,
            $this->filled('campaign_code') ? $this->string('campaign_code')->toString() : null,
            $this->string('device_name', self::DEFAULT_DEVICE_NAME)->toString() ?: self::DEFAULT_DEVICE_NAME,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
