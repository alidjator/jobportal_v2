<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\ReportVerification;

/**
 * What the public check says about a report number that was issued. No candidate, company, job, scale or recommendation. `document_erased` is true once the candidate's account was anonymised (the number, the
 * signer and the date stay, the PDF is gone and `sha256` is null).
 *
 * @property ReportVerification $resource
 */
final class ReportVerificationResource extends JsonResource
{
    public static $wrap = 'data';

    /** @return array{valid: true, provider: array{name: string, code: string}, psychologist: array{name: string}, signed_at: string, revision: string|null, superseded: bool, sha256: string|null, document_erased: bool} */
    public function toArray(Request $request): array
    {
        $check = $this->resource;

        return [
            'valid' => true,
            'provider' => ['name' => $check->providerName, 'code' => $check->providerCode],
            'psychologist' => ['name' => $check->psychologistName],
            'signed_at' => $check->signedAt->format(DateTimeInterface::ATOM),
            /** `R1`, `R2` for a revision, null for the first version. */
            'revision' => $check->revision === 0 ? null : 'R'.$check->revision,
            'superseded' => $check->superseded,
            'sha256' => $check->sha256,
            'document_erased' => $check->documentErased,
        ];
    }
}
