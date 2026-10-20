<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The activation report of one audience: `imported`, `deactivated`, `unverified`, `suppressed`, `eligible`; `activation` (first email, sent, reminders, bounced, complaints); `signed_in` (count and
 * the percent of sent and of imported); `milestones` at day 30, 60 and 90 from the first activation email (`signed_in` and `percent_of_sent` are null until the day is reached); `batches`; and, for
 * company owners, `companies_not_signed_in` by name.
 *
 * @property array<string, mixed> $resource
 */
final class ActivationProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
