<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Messages\ReportMessageView;
use Src\Assessment\Application\Messages\ReportThread;

/**
 * The questions about one signed report and the answers, oldest first.
 *
 * @property ReportThread $resource
 */
final class ReportThreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'report_id' => $this->resource->reportId,
            /** Whether a new message is taken now: the report is signed and its signing is less than 30 days old. */
            'open' => $this->resource->open,
            /** The moment the report stops taking messages; null while it is not signed. */
            'closes_at' => $this->resource->closesOn?->format(DateTimeInterface::ATOM),
            'messages' => array_map(static fn (ReportMessageView $view): array => [
                'id' => $view->message->id,
                /** @var 'company'|'psychologist' */
                'author_kind' => $view->message->author->value,
                'author_name' => $view->authorName,
                'mine' => $view->mine,
                'body' => $view->message->body,
                'created_at' => $view->message->createdAt?->format(DateTimeInterface::ATOM),
            ], $this->resource->messages),
        ];
    }
}
