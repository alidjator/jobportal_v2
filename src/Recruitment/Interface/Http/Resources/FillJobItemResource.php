<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\BulkItemResult;

/** What "mark as filled" did to one application: rejected, or skipped with a code. @property BulkItemResult $resource */
final class FillJobItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->resource->applicationId,
            'status' => $this->resource->wasMoved() ? 'rejected' : 'skipped',
            'code' => $this->resource->code,
        ];
    }
}
