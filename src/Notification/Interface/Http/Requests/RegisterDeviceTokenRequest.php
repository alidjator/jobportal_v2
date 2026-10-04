<?php

namespace Src\Notification\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Notification\Application\DeviceTokens\DevicePlatform;

final class RegisterDeviceTokenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', Rule::enum(DevicePlatform::class)],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }

    public function platform(): DevicePlatform
    {
        return DevicePlatform::from($this->string('platform')->toString());
    }
}
