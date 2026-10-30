<?php

namespace Src\JobInvitation\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobInvitation\Application\ClaimedEmailInvitation;

/**
 * The answer to a claim: the job to apply to and the CV the company holds for the person, if any (`has_cv`, `cv_name`), which they may take into their own resumes.
 *
 * @property ClaimedEmailInvitation $resource
 */
final class ClaimedEmailInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->resource->jobId,
            'job_title' => $this->resource->jobTitle,
            'company_name' => $this->resource->companyName,
            'channel' => $this->resource->channel->value,
            'has_cv' => $this->resource->hasCv,
            'cv_name' => $this->resource->cvName,
        ];
    }
}
