<?php

namespace Src\Recruitment\Domain;

interface ApplicationNoteRepository
{
    /** The note, only when it belongs to the application. */
    public function find(int $applicationId, int $noteId): ?ApplicationNote;

    /** @return list<ApplicationNote> oldest first */
    public function forApplication(int $applicationId): array;

    public function add(ApplicationNote $note): ApplicationNote;

    /** Saves the text and the edit time of a note that exists. */
    public function update(ApplicationNote $note): void;

    public function delete(int $noteId): void;
}
