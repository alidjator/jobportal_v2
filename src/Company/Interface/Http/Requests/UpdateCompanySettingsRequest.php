<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A JSON object of setting key => new value (`{"company.require_two_factor": true}`). The keys and values are checked against the settings catalog by the use case. */
final class UpdateCompanySettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->json()->all();
    }
}
