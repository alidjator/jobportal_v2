<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Licence\LicenceQueueItem;

/**
 * A submission in the admin's queue: the same fields as the psychologist sees, with who sent it and where they work.
 *
 * @property LicenceQueueItem $resource
 */
final class LicenceQueueItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource->row;
        $account = $this->resource->account;

        return [
            ...LicenceSubmissionResource::fields($row->submission),
            'psychologist_id' => $row->submission->psychologistId,
            'psychologist_name' => $account?->name,
            'psychologist_email' => $account?->email,
            'provider_id' => $row->providerId,
            'provider_name' => $row->providerName,
        ];
    }
}
