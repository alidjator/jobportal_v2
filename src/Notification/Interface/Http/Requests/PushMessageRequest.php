<?php

namespace Src\Notification\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PushMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
        ];
    }
}
