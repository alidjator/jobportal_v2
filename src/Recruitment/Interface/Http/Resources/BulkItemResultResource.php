<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\BulkItemResult;

/** @property BulkItemResult $resource */
final class BulkItemResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->resource->applicationId,
            'status' => $this->resource->wasMoved() ? 'moved' : 'skipped',
            'code' => $this->resource->code,
        ];
    }
}
