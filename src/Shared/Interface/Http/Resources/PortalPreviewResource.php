<?php

namespace Src\Shared\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Portal\PortalPreview;

/**
 * The token that shows a draft on the career site: the site sends it in the `X-Portal-Preview` header of `GET portal`.
 *
 * @property PortalPreview $resource
 */
final class PortalPreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['token' => $this->resource->token, 'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM)];
    }
}
