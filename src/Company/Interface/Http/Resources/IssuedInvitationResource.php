<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Domain\Invitation;

/**
 * An invitation that was just sent. The token is never returned: it only travels in the email.
 *
 * @property Invitation $resource
 */
final class IssuedInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'sent_at' => $this->resource->sentAt->format(DateTimeInterface::ATOM),
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
        ];
    }
}
