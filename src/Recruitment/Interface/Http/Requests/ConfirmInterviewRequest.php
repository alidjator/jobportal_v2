<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\RecordingConsent;

/** The job seeker's answer to the recording of an interview that the team marked as recorded; it is ignored for any other interview. */
final class ConfirmInterviewRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['recording_consent' => RecordingConsentField::rules()];
    }

    public function recordingConsent(): ?RecordingConsent
    {
        return RecordingConsentField::value($this->validated('recording_consent'));
    }
}
