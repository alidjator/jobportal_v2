<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplyState;

/**
 * Whether the job seeker could apply to the job now. Read only: the apply request still decides.
 *
 * @property ApplyState $resource
 */
final class ApplyStateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'can_apply' => $this->resource->canApply(),
            /** @var list<array{code: string, missing?: list<string>, reapply_on?: string}> why not, in the order the apply request checks: `email-not-verified`, `profile-incomplete` (`missing` lists the parts and extra items), `already-applied` (`reapply_on` is the day a waiting period ends), `application-limit-reached`, `applications-unavailable` */
            'reasons' => $this->resource->reasons,
            /** The job seeker's live application for the job, in whatever stage; null when there is none. */
            'application' => $this->resource->application === null ? null : [
                'id' => $this->resource->application['id'],
                'status' => $this->resource->application['status'],
                'applied_at' => $this->resource->application['applied_at']->format(DATE_ATOM),
            ],
            'applications_left_today' => $this->resource->applicationsLeftToday,
        ];
    }
}
