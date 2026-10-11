<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Delivery\MyInvitationView;
use Src\Assessment\Domain\Section;

/**
 * A test the job seeker was asked to take. One invitation also carries the instructions and the sections (title, format and length), never a question.
 *
 * @property MyInvitationView $resource
 */
final class MyInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource->invitation;
        $test = $this->resource->test;

        $data = [
            'id' => $invitation->id,
            'application_id' => $invitation->applicationId,
            'job' => ['id' => $invitation->jobId, 'title' => $this->resource->jobTitle],
            'company' => ['id' => $invitation->companyId, 'name' => $this->resource->companyName],
            'test' => ['id' => $test->id, 'title' => $test->title(), 'kind' => $test->kind()->value, 'description' => $test->description()],
            'status' => $invitation->status->value,
            'starts_at' => $invitation->startsAt->format(DateTimeInterface::ATOM),
            'due_at' => $invitation->dueAt->format(DateTimeInterface::ATOM),
            'created_at' => $invitation->createdAt?->format(DateTimeInterface::ATOM),
        ];

        if ($this->resource->sections === null) {
            return $data;
        }

        $data['test']['instructions'] = $test->instructions();
        $data['sections'] = array_map(static fn (Section $section): array => [
            'title' => $section->title,
            'format' => $section->format->value,
            'duration_seconds' => $section->durationSeconds,
        ], $this->resource->sections);
        $data['total_seconds'] = array_sum(array_map(static fn (Section $section): int => $section->durationSeconds, $this->resource->sections));

        return $data;
    }
}
