<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\RecordingConsent;

/** The job seeker's answer to the recording of an interview that the team marked as recorded; it is ignored for any other interview. A voice interview also needs `phone` and a video interview may bring a `fallback_phone`. */
final class ConfirmInterviewRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['recording_consent' => RecordingConsentField::rules()] + PhoneFields::rules();
    }

    public function phone(): ?string
    {
        return $this->validated('phone');
    }

    public function fallbackPhone(): ?string
    {
        return $this->validated('fallback_phone');
    }

    public function recordingConsent(): ?RecordingConsent
    {
        return RecordingConsentField::value($this->validated('recording_consent'));
    }
}
