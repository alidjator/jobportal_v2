<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\PsychologistInvitation;

/**
 * An invitation of a psychologist. The token is never returned: it only travels in the email.
 *
 * @property PsychologistInvitation $resource
 */
final class PsychologistInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'provider_id' => $this->resource->providerId,
            'email' => $this->resource->email,
            'name' => $this->resource->name,
            'str_number' => $this->resource->strNumber,
            'str_valid_until' => $this->resource->strValidUntil->format('Y-m-d'),
            'silp_number' => $this->resource->silpNumber,
            'silp_valid_until' => $this->resource->silpValidUntil->format('Y-m-d'),
            'sent_at' => $this->resource->sentAt->format(DateTimeInterface::ATOM),
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
        ];
    }
}
