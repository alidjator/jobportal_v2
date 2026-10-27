<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\JobPresetOption;

/**
 * A preset in the menu of a job: the preset as the library shows it, and whether its level hint is the level of the job.
 *
 * @property JobPresetOption $resource
 */
final class JobScreeningPresetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new ScreeningPresetResource($this->resource->preset))->toArray($request),
            'suggested' => $this->resource->suggested,
        ];
    }
}
