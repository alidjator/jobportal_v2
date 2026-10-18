<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\OfferLetterUpload;

/**
 * A company letter that waits for its offer: `upload_id` goes into `letter_upload_id` of the offer request before `expires_at`.
 *
 * @property OfferLetterUpload $resource
 */
final class OfferLetterUploadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'upload_id' => $this->resource->id,
            'application_id' => $this->resource->applicationId,
            'size_bytes' => $this->resource->sizeBytes,
            'sha256' => $this->resource->sha256,
            'expires_at' => $this->resource->expiresAt()->format(DATE_ATOM),
        ];
    }
}
