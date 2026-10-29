<?php

namespace Src\Job\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\JobRequisitionChoices;
use Src\Shared\Application\Workforce\OpenRequisition;

/**
 * The choices for the form of a new job: `required` says that the tenant opens its jobs from a requisition (the job then names one and its vacancies), and `requisitions` are the ones still open, with the hires left (`remaining`).
 *
 * @property JobRequisitionChoices $resource
 */
final class JobRequisitionChoicesResource extends JsonResource
{
    /** @return array{required: bool, requisitions: list<array{id: int, position_title: string, hires: int, remaining: int, target_join_date: string}>} */
    public function toArray(Request $request): array
    {
        return [
            'required' => $this->resource->required,
            /** @var list<array{id: int, position_title: string, hires: int, remaining: int, target_join_date: string}> */
            'requisitions' => array_map(static fn (OpenRequisition $requisition): array => [
                'id' => $requisition->id,
                'position_title' => $requisition->positionTitle,
                'hires' => $requisition->hires,
                'remaining' => $requisition->remaining,
                'target_join_date' => $requisition->targetJoinDate,
            ], $this->resource->requisitions),
        ];
    }
}
