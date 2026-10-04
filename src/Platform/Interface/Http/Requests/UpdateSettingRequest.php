<?php

namespace Src\Platform\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body is `{"value": ...}`; the type and limits of the value depend on the setting and are checked against the catalog. */
final class UpdateSettingRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['value' => ['present']];
    }

    public function value(): mixed
    {
        return $this->input('value');
    }
}
