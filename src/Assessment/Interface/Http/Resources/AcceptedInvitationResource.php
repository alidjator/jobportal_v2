<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Invitations\AcceptedInvitation;

/** @property AcceptedInvitation $resource */
final class AcceptedInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->resource->email,
            'provider_name' => $this->resource->providerName,
            'next' => 'sign_in',
        ];
    }
}
