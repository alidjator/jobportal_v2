<?php

namespace Src\Identity\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\TwoFactor\TwoFactorChallengeIssued;

/**
 * The answer to a right password when a second factor is still due: a challenge to finish with a code, never a token. The enrolment data (secret, link and QR
 * code) is there only when the account has no confirmed factor yet.
 *
 * @property TwoFactorChallengeIssued $resource
 */
final class TwoFactorChallengeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'status' => $this->resource->enrolment === null ? 'two_factor_required' : 'two_factor_enrolment_required',
            'challenge_token' => $this->resource->token,
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
        ];

        if ($this->resource->enrolment !== null) {
            $data['enrolment'] = [
                'secret' => $this->resource->enrolment->secret,
                'otpauth_url' => $this->resource->enrolment->otpauthUrl,
                'qr_code' => $this->resource->enrolment->qrCode,
            ];
        }

        return $data;
    }
}
