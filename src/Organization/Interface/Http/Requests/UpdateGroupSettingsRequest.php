<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A JSON object of setting key => new value (`{"group.require_two_factor": false}`). The keys and values are checked against the settings catalog by the use case. */
final class UpdateGroupSettingsRequest extends FormRequest
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
