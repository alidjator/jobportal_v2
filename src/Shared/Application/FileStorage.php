<?php

namespace Src\Shared\Application;

/**
 * The private file storage (one disk, never publicly reachable). Files are referenced by the path stored in the database.
 * Only what the contexts need so far is declared; upload, streaming and signed URLs are added with the first file feature.
 */
interface FileStorage
{
    /** Deletes the file. A path that does not exist is not an error, so deletions can be retried safely. */
    public function delete(string $path): void;
}
