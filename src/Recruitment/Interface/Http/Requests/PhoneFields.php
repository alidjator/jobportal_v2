<?php

namespace Src\Recruitment\Interface\Http\Requests;

/** The numbers the job seeker gives when they answer a schedule: `phone` (the number a voice interview is dialled on) and `fallback_phone` (an optional number for a video interview). Whether they are needed and valid depends on the interview, so the domain checks them (422 `invalid-phone`). */
final class PhoneFields
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return ['phone' => ['nullable', 'string', 'max:30'], 'fallback_phone' => ['nullable', 'string', 'max:30']];
    }
}
