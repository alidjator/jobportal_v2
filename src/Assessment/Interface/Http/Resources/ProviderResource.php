<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\Provider;

/** @property Provider $resource */
final class ProviderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code(),
            'name' => $this->resource->name(),
            'legal_name' => $this->resource->legalName(),
            'status' => $this->resource->status()->value,
            'auto_issue_allowed' => $this->resource->autoIssueAllowed(),
            'address' => $this->resource->identity()->address,
            'practice_licence_number' => $this->resource->identity()->practiceLicenceNumber,
            'phone' => $this->resource->identity()->phone,
            'email' => $this->resource->identity()->email,
            /** The working days (1 to 10) the psychologists take to review a report. */
            'review_target_days' => $this->resource->reviewTargetDays(),
            'has_logo' => $this->resource->identity()->logoPath !== null,
            'created_at' => $this->resource->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
