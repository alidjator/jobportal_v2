<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Domain\DataExport;
use Src\JobSeeker\Domain\DataExportStatus;
use Src\Shared\Infrastructure\ContextServiceProvider;

/**
 * A request for a copy of the owner's personal data. A ready copy past its 24 hours shows as `expired` even before the daily cleanup has closed it; the storage
 * path is never shown, the copy is downloaded through the download link while it is ready.
 *
 * @property DataExport $resource
 */
final class DataExportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $now = now()->toDateTimeImmutable();
        $downloadable = $this->resource->isDownloadable($now);
        $status = $this->resource->status === DataExportStatus::Ready && ! $downloadable ? DataExportStatus::Expired : $this->resource->status;

        return [
            'status' => $status->value,
            'requested_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'ready_at' => $this->resource->readyAt?->format(DateTimeInterface::ATOM),
            'expires_at' => $this->resource->expiresAt?->format(DateTimeInterface::ATOM),
            'size_bytes' => $this->resource->sizeBytes,
            'download_url' => $downloadable ? url('/'.ContextServiceProvider::API_PREFIX.'/me/data-export/download') : null,
        ];
    }
}
