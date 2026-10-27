<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\Terms\TermsRequirement;
use Src\Identity\Interface\Http\Payloads\TermsStatusPayload;

/**
 * Whether the caller accepted every required legal document, and the state of each.
 *
 * @property TermsStatusPayload $resource
 */
final class TermsStatusResource extends JsonResource
{
    /** @return array{accepted: bool, documents: list<array{type: string, version: int, accepted: bool}>} */
    public function toArray(Request $request): array
    {
        return [
            'accepted' => ! in_array(false, array_map(static fn (TermsRequirement $requirement): bool => $requirement->accepted, $this->resource->requirements), true),
            /** @var list<array{type: string, version: int, accepted: bool}> */
            'documents' => array_map(static fn (TermsRequirement $requirement): array => ['type' => $requirement->type, 'version' => $requirement->version, 'accepted' => $requirement->accepted], $this->resource->requirements),
        ];
    }
}
