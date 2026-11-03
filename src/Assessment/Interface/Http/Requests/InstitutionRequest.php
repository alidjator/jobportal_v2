<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Domain\ReviewTarget;

/** The body of a lead editing their institution: the identity fields of `docs/design/phase-8-provider-institutions.md` section 3 and the review target of section 5. Only the fields that are sent change; a blank one clears. */
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
            'review_target_days' => ['sometimes', 'integer', 'between:'.ReviewTarget::MIN_DAYS.','.ReviewTarget::MAX_DAYS],
        ];
    }

    /** The working days the psychologists take to review a report, when it was sent. */
    public static function reviewTargetOf(FormRequest $request): ?int
    {
        return $request->has('review_target_days') ? $request->integer('review_target_days') : null;
    }

    public function reviewTargetDays(): ?int
    {
        return self::reviewTargetOf($this);
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
