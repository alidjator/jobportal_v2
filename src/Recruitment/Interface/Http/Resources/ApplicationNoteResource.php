<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\NoteView;

/**
 * An internal note: its text, its author (null name once the account is erased) and its times. Only the company's team gets it.
 *
 * @property NoteView $resource
 */
final class ApplicationNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $note = $this->resource->note;

        return [
            'id' => $note->id,
            'body' => $note->body,
            'author' => ['id' => $note->authorId, 'name' => $this->resource->authorName],
            'created_at' => $note->createdAt->format(DATE_ATOM),
            'edited_at' => $note->editedAt?->format(DATE_ATOM),
        ];
    }
}
