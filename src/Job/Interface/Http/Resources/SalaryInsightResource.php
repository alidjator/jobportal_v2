<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Insight\SalaryInsight;

/**
 * What the jobs pay for a role. With no figures only the role, the window and the time are shown: no count, since a count below the minimum is never shown.
 *
 * @property SalaryInsight $resource
 */
final class SalaryInsightResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $insight = $this->resource;
        $base = [
            'available' => $insight->figures !== null,
            'currency' => 'IDR',
            'period' => 'monthly',
            'window_months' => $insight->windowMonths,
            'role' => $insight->role,
        ];

        if ($insight->figures === null) {
            return [...$base, 'updated_at' => $insight->updatedAt->format(DateTimeInterface::ATOM)];
        }

        return [
            ...$base,
            'scope' => [
                'level' => $insight->level,
                'category' => $insight->category,
                'province' => $insight->province,
                'city' => $insight->city,
                'experience_level' => $insight->experienceLevel,
            ],
            'widened' => $insight->widened,
            'jobs' => $insight->figures->jobs,
            'p25' => $insight->figures->p25,
            'median' => $insight->figures->median,
            'p75' => $insight->figures->p75,
            'updated_at' => $insight->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
