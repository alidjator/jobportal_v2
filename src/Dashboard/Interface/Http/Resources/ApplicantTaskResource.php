<?php

namespace Src\Dashboard\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\ApplicantTaskView;

/**
 * One thing that waits for the job seeker. `subject_id` is the id of the thing itself (the interview, the offer, the test invitation, the job invitation or the check); `application_id` is null for an invitation to
 * apply. `due_at` is when it stops being possible, null when it never does.
 *
 * @property ApplicantTaskView $resource
 */
final class ApplicantTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $task = $this->resource;

        return [
            /** @var 'interview_confirm'|'offer_answer'|'assessment_take'|'job_invitation'|'check_consent' */
            'key' => $task->key,
            'subject_id' => $task->subjectId,
            'application_id' => $task->applicationId,
            'job' => ['id' => $task->jobId, 'title' => $task->jobTitle],
            'company_name' => $task->companyName,
            'due_at' => $task->dueAt?->format(DateTimeInterface::ATOM),
            'created_at' => $task->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
