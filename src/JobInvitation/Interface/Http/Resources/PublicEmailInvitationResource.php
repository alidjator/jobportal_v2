<?php

namespace Src\JobInvitation\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobInvitation\Application\PublicEmailInvitation;

/**
 * What the person reads when they open the link: the company, the job, the note and the channel; `open` says whether the link still works and `job_live` whether the job still takes applications.
 *
 * @property PublicEmailInvitation $resource
 */
final class PublicEmailInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->resource->companyName,
            'job_id' => $this->resource->jobId,
            'job_title' => $this->resource->jobTitle,
            'note' => $this->resource->note,
            'channel' => $this->resource->channel->value,
            'open' => $this->resource->open,
            'job_live' => $this->resource->jobLive,
        ];
    }
}
