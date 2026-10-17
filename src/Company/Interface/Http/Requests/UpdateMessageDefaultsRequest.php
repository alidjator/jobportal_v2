<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Domain\MessageDefaults;

/** The default texts to change; a scenario that is missing stays as it is, and a null or blank text clears it. */
final class UpdateMessageDefaultsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [];

        foreach (MessageDefaults::MAX_LENGTH as $scenario => $max) {
            $rules[$scenario] = ['sometimes', 'nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /** @return array<string, ?string> */
    public function changes(): array
    {
        $changes = [];

        foreach (MessageDefaults::SCENARIOS as $scenario) {
            if ($this->has($scenario)) {
                $value = $this->input($scenario);
                $changes[$scenario] = $value === null ? null : (string) $value;
            }
        }

        return $changes;
    }
}
