<?php

namespace Src\Dashboard\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\AnalyticsReportView;

/** @property AnalyticsReportView $resource */
final class AnalyticsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'report' => $this->resource->report,
            'from' => $this->resource->from,
            'to' => $this->resource->to,
            'min_group_size' => $this->resource->minGroup,
            'generated_at' => $this->resource->generatedAt,
            'tables' => (object) array_column($this->resource->tables, null, 'name'),
        ];
    }
}
