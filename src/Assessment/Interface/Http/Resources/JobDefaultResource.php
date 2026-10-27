<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Defaults\JobDefaultView;

/**
 * The default test of a job for one assessment stage. `problem` is the error code of the rule the default breaks now (a test that was archived, a battery the company lost, an
 * assessor who left the job, a removed stage), null while it works: a move into the stage would be refused with `assessment-default-invalid` until it is fixed.
 *
 * @property JobDefaultView $resource
 */
final class JobDefaultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'stage_id' => $this->resource->stageId,
            'stage_name' => $this->resource->stageName,
            'test_id' => $this->resource->testId,
            'test_title' => $this->resource->testTitle,
            'due_days' => $this->resource->dueDays,
            'assessors' => $this->resource->assessors,
            /** The score a skill test must reach to move the application on, and where it goes. */
            'advance' => $this->resource->advance,
            'problem' => $this->resource->problem,
            'updated_at' => $this->resource->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
