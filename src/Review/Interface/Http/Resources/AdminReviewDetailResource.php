<?php

namespace Src\Review\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\AdminReviewDetail;
use Src\Review\Domain\Report;

/** One review for the moderator with every report on it. @property AdminReviewDetail $resource */
final class AdminReviewDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new AdminReviewResource($this->resource->view))->toArray($request),
            'reports' => array_map(static fn (Report $report): array => ReportPayload::of($report), $this->resource->reports),
        ];
    }
}
