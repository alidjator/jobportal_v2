<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\RecordingConsent;

/** The `recording_consent` field shared by the requests that answer a schedule: `granted` or `refused` (`withdrawn` is a separate action). */
final class RecordingConsentField
{
    /** @return list<mixed> */
    public static function rules(): array
    {
        return ['nullable', 'string', Rule::in([RecordingConsent::Granted->value, RecordingConsent::Refused->value])];
    }

    public static function value(mixed $value): ?RecordingConsent
    {
        return is_string($value) ? RecordingConsent::from($value) : null;
    }
}
