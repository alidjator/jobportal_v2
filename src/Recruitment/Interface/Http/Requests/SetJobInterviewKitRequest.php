<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The kit a job preselects for an interview stage. */
final class SetJobInterviewKitRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['kit_id' => ['required', 'integer', 'min:1']];
    }

    public function kitId(): int
    {
        return $this->integer('kit_id');
    }
}
