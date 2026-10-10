<?php

namespace Src\Newsletter\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `token` is the only field: the secret of a confirmation or an unsubscribe link. */
final class TokenRequest extends FormRequest
{
    private const int MAX_LENGTH = 200;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:'.self::MAX_LENGTH]];
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }
}
