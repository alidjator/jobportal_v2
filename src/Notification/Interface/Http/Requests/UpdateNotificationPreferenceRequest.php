<?php

namespace Src\Notification\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationPreferenceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean']];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }
}
