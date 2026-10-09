<?php

namespace Src\Messaging\Domain;

/** What messaging holds of a user whose account was anonymised. */
interface MessagingDataEraser
{
    /** @return list<string> the paths of the files this user uploaded */
    public function attachmentPaths(int $userId): array;

    /** Deletes the file rows the user uploaded and clears the text of their messages; the messages stay for the other side's count and read as sent by a deleted user. */
    public function clearPersonalData(int $userId): void;
}
