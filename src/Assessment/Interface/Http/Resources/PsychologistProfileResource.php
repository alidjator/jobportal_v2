<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Provider\PsychologistProfile;

/** @property PsychologistProfile $resource */
final class PsychologistProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $psychologist = $this->resource->psychologist;
        $provider = $this->resource->provider;

        return [
            'id' => $psychologist->id,
            'name' => $this->resource->account?->name,
            'email' => $this->resource->account?->email,
            'provider' => ['id' => $provider->id, 'name' => $provider->name(), 'status' => $provider->status()->value],
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
