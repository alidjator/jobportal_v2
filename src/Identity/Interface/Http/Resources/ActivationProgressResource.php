<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Interface\Http\Payloads\ActivationProgressPayload;

/**
 * The activation report of one audience: `imported`, `deactivated`, `unverified`, `suppressed`, `eligible`; `activation` (first email, sent, reminders, bounced, complaints); `signed_in` (count and
 * the percent of sent and of imported); `milestones` at day 30, 60 and 90 from the first activation email (`signed_in` and `percent_of_sent` are null until the day is reached); `batches`; and, for
 * company owners, `companies_not_signed_in` by name.
 *
 * @property ActivationProgressPayload $resource
 */
final class ActivationProgressResource extends JsonResource
{
    /**
     * @return array{
     *     audience: string,
     *     imported: int,
     *     deactivated: int,
     *     unverified: int,
     *     suppressed: int,
     *     eligible: int,
     *     activation: array{first_sent_at: string|null, sent: int, reminders_sent: int, bounced: int, complaints: int},
     *     signed_in: array{count: int, percent_of_sent: float|null, percent_of_imported: float|null},
     *     milestones: list<array{day: int, date: string, reached: bool, signed_in: int|null, percent_of_sent: float|null}>,
     *     batches: list<array{batch: string, sent_at: string, sent: int, bounced: int, complaints: int, signed_in: int}>,
     *     companies_not_signed_in?: list<array{company_id: int, name: string}>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource->report;
    }
}
