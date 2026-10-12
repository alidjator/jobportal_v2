<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\RecordingConsent;

/** The start the job seeker proposes instead of the scheduled one (ISO 8601 with an offset or `Z`), and for a recorded interview their answer to the recording. */
final class ProposeInterviewTimeRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['starts_at' => InterviewFields::rules()['starts_at'], 'recording_consent' => RecordingConsentField::rules()];
    }

    public function startsAt(): DateTimeImmutable
    {
        return InterviewFields::time($this->string('starts_at')->toString());
    }

    public function recordingConsent(): ?RecordingConsent
    {
        return RecordingConsentField::value($this->validated('recording_consent'));
    }
}
