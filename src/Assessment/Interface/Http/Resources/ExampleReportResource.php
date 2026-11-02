<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\ExampleReport;

/**
 * The example report a battery holds: the name of the file, its size and when it was uploaded. The file itself is for companies (`GET company/assessment/catalog/{id}/example-report`).
 *
 * @property ExampleReport $resource
 */
final class ExampleReportResource extends JsonResource
{
    /** @return array{name: string, size_bytes: int, uploaded_at: string} */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->originalName,
            'size_bytes' => $this->resource->sizeBytes,
            'uploaded_at' => $this->resource->uploadedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
