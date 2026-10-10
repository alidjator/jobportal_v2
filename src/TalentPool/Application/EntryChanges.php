<?php

namespace Src\TalentPool\Application;

/** What a member changes on a saved candidate: the category and/or the note. A part that is not sent stays as it is; `null` clears it. */
final readonly class EntryChanges
{
    public function __construct(
        public bool $changesCategory,
        public ?int $categoryId,
        public bool $changesNote,
        public ?string $note,
    ) {}
}
