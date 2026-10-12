<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\JobKitDefault;

/**
 * The default kit of a job for one interview stage.
 *
 * @property JobKitDefault $resource
 */
final class JobInterviewKitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'stage' => ['id' => $this->resource->stageId, 'name' => $this->resource->stageName],
            'kit' => ['id' => $this->resource->kitId, 'name' => $this->resource->kitName],
        ];
    }
}
