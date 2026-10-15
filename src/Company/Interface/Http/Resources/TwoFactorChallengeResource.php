<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Accounts\TwoFactorChallengeRequired;

/**
 * The answer after accepting an invitation as a company admin: a challenge to set up the second factor, shaped like the one of the sign-in. The client
 * finishes at `POST auth/two-factor`.
 *
 * @property TwoFactorChallengeRequired $resource
 */
final class TwoFactorChallengeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'status' => $this->resource->isEnrolment() ? 'two_factor_enrolment_required' : 'two_factor_required',
            'challenge_token' => $this->resource->token,
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
        ];

        if ($this->resource->isEnrolment()) {
            $data['enrolment'] = ['secret' => $this->resource->secret, 'otpauth_url' => $this->resource->otpauthUrl, 'qr_code' => $this->resource->qrCode];
        }

        return $data;
    }
}
