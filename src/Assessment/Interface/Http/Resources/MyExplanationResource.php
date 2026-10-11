<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Reports\MyExplanation;

/** The explanation of a signed psychological result, as the candidate reads it. @property MyExplanation $resource */
final class MyExplanationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'invitation_id' => $this->resource->invitationId,
            'test_title' => $this->resource->testTitle,
            'report_number' => $this->resource->reportNumber,
            'signed_at' => $this->resource->signedAt->format(DateTimeInterface::ATOM),
            'signer_name' => $this->resource->signerName,
            'explanation' => $this->resource->explanation,
            'scales' => $this->resource->scales,
        ];
    }
}
