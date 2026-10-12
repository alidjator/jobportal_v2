<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\VideoTake;

/**
 * One recording of the candidate. The file itself is never handed back to the candidate.
 *
 * @property VideoTake $resource
 */
final class VideoTakeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return self::summary($this->resource);
    }

    /** @return array<string, mixed> */
    public static function summary(VideoTake $take): array
    {
        return [
            'id' => $take->id,
            'number' => $take->number,
            'status' => $take->status->value,
            'chunks_received' => $take->chunkCount,
            'bytes_received' => $take->sizeBytes,
            'started_at' => $take->startedAt->format(DateTimeInterface::ATOM),
            'deadline_at' => $take->deadlineAt->format(DateTimeInterface::ATOM),
            'completed_at' => $take->completedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
