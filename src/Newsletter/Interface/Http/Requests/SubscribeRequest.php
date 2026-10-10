<?php

namespace Src\Newsletter\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `email` is the only field. */
final class SubscribeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:254']];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }
}
