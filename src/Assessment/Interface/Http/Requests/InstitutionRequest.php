<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body of a lead editing their institution: the identity fields of `docs/design/phase-8-provider-institutions.md` section 3. Only the fields that are sent change; a blank one clears. */
final class InstitutionRequest extends FormRequest
{
    private const array FIELDS = ['address', 'practice_licence_number', 'phone', 'email'];

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return self::identityRules();
    }

    /** @return array<string, list<string>> */
    public static function identityRules(): array
    {
        return [
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'practice_licence_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string|null> the identity fields that were sent */
    public static function changesOf(FormRequest $request): array
    {
        $changes = [];

        foreach (self::FIELDS as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $changes[$field] = is_string($value) ? $value : null;
            }
        }

        return $changes;
    }

    /** @return array<string, string|null> */
    public function changes(): array
    {
        return self::changesOf($this);
    }
}
