<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Admin\PsychologistListItem;

/** @property PsychologistListItem $resource */
final class PsychologistResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $psychologist = $this->resource->psychologist;
        $account = $this->resource->account;

        return [
            'id' => $psychologist->id,
            'provider_id' => $psychologist->providerId,
            'user_id' => $psychologist->userId,
            'name' => $account?->name,
            'email' => $account?->email,
            'account_status' => $account?->status,
            'last_login_at' => $account?->lastLoginAt?->format(DateTimeInterface::ATOM),
            'str_number' => $psychologist->strNumber(),
            'str_valid_until' => $psychologist->strValidUntil()?->format('Y-m-d'),
            'silp_number' => $psychologist->silpNumber(),
            'silp_valid_until' => $psychologist->silpValidUntil()?->format('Y-m-d'),
            'role' => $psychologist->role()->value,
            'status' => $psychologist->status()->value,
            'can_sign_reports' => $this->resource->canSignReports,
        ];
    }
}
