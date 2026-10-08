<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** The start the job seeker proposes instead of the scheduled one (ISO 8601 with an offset or `Z`). */
final class ProposeInterviewTimeRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['starts_at' => InterviewFields::rules()['starts_at']];
    }

    public function startsAt(): DateTimeImmutable
    {
        return InterviewFields::time($this->string('starts_at')->toString());
    }
}
