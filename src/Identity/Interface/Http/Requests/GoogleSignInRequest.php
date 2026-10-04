<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Application\Auth\GoogleSignInCommand;
use Src\Shared\Application\RequestContext;

final class GoogleSignInRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'api';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'campaign_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ];
    }

    public function toCommand(RequestContext $context): GoogleSignInCommand
    {
        return new GoogleSignInCommand(
            $this->string('id_token')->toString(),
            $context->language,
            $this->filled('campaign_code') ? $this->string('campaign_code')->toString() : null,
            $this->string('device_name', self::DEFAULT_DEVICE_NAME)->toString() ?: self::DEFAULT_DEVICE_NAME,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
