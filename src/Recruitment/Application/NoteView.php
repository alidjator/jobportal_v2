<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationNote;

/** A note with the name of its author (null when the account no longer has a name). */
final readonly class NoteView
{
    public function __construct(public ApplicationNote $note, public ?string $authorName) {}
}
