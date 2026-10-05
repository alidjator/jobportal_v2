<?php

namespace Src\Shared\Application;

/**
 * The private file storage (one disk, never publicly reachable). Files are referenced by the path stored in the database, and are only
 * handed out through API endpoints that check access. Streaming of very large files and signed URLs are added when a feature needs them.
 */
interface FileStorage
{
    /** Writes the file, replacing any file at that path. */
    public function put(string $path, string $contents): void;

    /** The contents of the file, or null when there is no file at that path. */
    public function get(string $path): ?string;

    /** Deletes the file. A path that does not exist is not an error, so deletions can be retried safely. */
    public function delete(string $path): void;
}
