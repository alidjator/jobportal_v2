<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\Norm;

/** @property Norm $resource */
final class NormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'scale_key' => $this->resource->scaleKey,
            'raw_min' => $this->resource->rawMin,
            'raw_max' => $this->resource->rawMax,
            'standard_score' => $this->resource->standardScore,
            'band' => $this->resource->band,
            'explanation' => $this->resource->explanation,
            'age_min' => $this->resource->ageMin,
            'age_max' => $this->resource->ageMax,
            'education_rank_min' => $this->resource->educationRankMin,
            'education_rank_max' => $this->resource->educationRankMax,
        ];
    }
}
