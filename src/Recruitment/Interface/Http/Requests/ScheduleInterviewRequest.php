<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\InterviewInput;

/** The time, duration, format, place or link and interviewer of an interview. */
final class ScheduleInterviewRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return InterviewFields::rules();
    }

    public function toInput(): InterviewInput
    {
        return InterviewFields::input($this->validated());
    }
}
