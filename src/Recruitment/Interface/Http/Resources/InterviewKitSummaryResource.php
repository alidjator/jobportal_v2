<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\InterviewKit;

/**
 * A kit in the library list, without its questions.
 *
 * @property InterviewKit $resource
 */
final class InterviewKitSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /** @return array<string, mixed> */
    public static function fields(InterviewKit $kit): array
    {
        return [
            'id' => $kit->id,
            'name' => $kit->name,
            'questions_count' => count($kit->questions),
            'created_by' => $kit->createdBy,
            'updated_by' => $kit->updatedBy,
            'created_at' => $kit->createdAt->format(DATE_ATOM),
            'updated_at' => $kit->updatedAt->format(DATE_ATOM),
        ];
    }
}
