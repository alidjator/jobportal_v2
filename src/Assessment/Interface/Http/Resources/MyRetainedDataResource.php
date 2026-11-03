<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Archive\MyRetainedData;

/**
 * What the erasure of the account would keep for an institution.
 *
 * @property MyRetainedData $resource
 */
final class MyRetainedDataResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** The raw data of the psychological examinations that stays with the institution after the erasure and for how many years; null while the retention switch is off. */
            'retention' => $this->resource->years === null ? null : [
                'assessment_examinations' => ['years' => $this->resource->years, 'count' => $this->resource->examinations],
            ],
        ];
    }
}
