<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Only the shape of the request is checked here; the slug rules (format, reserved names, taken) live in the use case. */
final class ChangeCompanySlugRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['slug' => ['required', 'string', 'max:100']];
    }

    public function slug(): string
    {
        return $this->string('slug')->toString();
    }
}
