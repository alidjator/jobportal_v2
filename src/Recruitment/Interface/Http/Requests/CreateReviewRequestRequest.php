<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\ReviewRequest;

/** The shape only: that the applications and reviewers belong to the job and the due date is within its range is checked by the use case. */
final class CreateReviewRequestRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'application_ids' => ['required', 'array', 'min:1', 'max:'.ReviewRequest::APPLICATIONS_MAX],
            'application_ids.*' => ['integer', 'min:1', 'distinct'],
            'reviewer_ids' => ['nullable', 'array', 'max:50'],
            'reviewer_ids.*' => ['integer', 'min:1', 'distinct'],
            'due_on' => ['required', 'string', 'date_format:Y-m-d'],
            'message' => ['nullable', 'string', 'max:'.ReviewRequest::MESSAGE_MAX],
        ];
    }

    /** @return list<int> */
    public function applicationIds(): array
    {
        return array_values(array_map('intval', $this->array('application_ids')));
    }

    /** @return ?list<int> null: every hiring manager of the job */
    public function reviewerIds(): ?array
    {
        return $this->has('reviewer_ids') && $this->input('reviewer_ids') !== null ? array_values(array_map('intval', $this->array('reviewer_ids'))) : null;
    }

    public function dueOn(): string
    {
        return $this->string('due_on')->toString();
    }

    public function message(): ?string
    {
        $message = $this->input('message');

        return is_string($message) ? $message : null;
    }
}
