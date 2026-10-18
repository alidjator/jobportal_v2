<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\Account\CurrentUserView;

/** The account of `GET me`: the fields of a sign-in response and `email_deliverable`, false while the address is on the suppression list (the front end asks the person to check or change it). @property CurrentUserView $resource */
final class CurrentUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'id' => $user->id,
            'type' => $user->type->value,
            'name' => $user->name(),
            'email' => $user->email(),
            'email_verified' => $user->isEmailVerified(),
            'status' => $user->status()->value,
            'locale' => $user->locale(),
            'has_avatar' => $user->avatarPath() !== null,
            'email_deliverable' => $this->resource->emailDeliverable,
        ];
    }
}
