<?php

namespace Src\TalentPool\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Recruitment\PoolApplicationRef;
use Src\TalentPool\Application\EntryDetail;

/**
 * The entry page: the summary of the list plus the applications of the person to the company that the member may see and, for a person kept through consent alone,
 * `profile` (the snapshot of their latest consenting application, without contact data). A findable person's full profile is `GET company/candidates/{slug}` with
 * `candidate.slug`.
 *
 * @property EntryDetail $resource
 */
final class EntryDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new EntryResource($this->resource->view))->toArray($request),
            /** @var array<string, mixed>|null the snapshot without contact data; null for a findable person and for an entry that is not available */
            'profile' => $this->resource->profile,
            /** @var list<array{id: int|null, job_title: string|null, status: string, applied_at: string}> */
            'applications' => array_map(static fn (PoolApplicationRef $application): array => [
                'id' => $application->id,
                'job_title' => $application->jobTitle,
                'status' => $application->status,
                'applied_at' => $application->appliedAt->format(DateTimeInterface::ATOM),
            ], $this->resource->applications),
        ];
    }
}
