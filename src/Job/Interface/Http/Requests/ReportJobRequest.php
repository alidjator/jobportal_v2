<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Job\Application\Reports\ReportJob;
use Src\Job\Domain\JobReportReason;

/** Report a job: `reason` (one of the listed values) and a `note` of at most 1,000 characters, required for `other`. The use case checks the note against the reason. */
final class ReportJobRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(JobReportReason::class)],
            'note' => ['nullable', 'string', 'max:'.ReportJob::NOTE_MAX],
        ];
    }

    public function reason(): JobReportReason
    {
        return JobReportReason::from($this->string('reason')->toString());
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }
}
