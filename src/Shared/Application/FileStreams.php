<?php

namespace Src\Shared\Application;

/**
 * Large files on the private disk, written and read as streams so a recording never sits in memory. Paths are the same as {@see FileStorage}, which also deletes them.
 */
interface FileStreams
{
    /**
     * Writes the stream to the file, replacing any file at that path.
     *
     * @param  resource  $stream
     */
    public function putStream(string $path, $stream): void;

    /**
     * A readable stream of the file, or null when there is none. The caller closes it.
     *
     * @return resource|null
     */
    public function readStream(string $path);

    /** The size of the file in bytes, or null when there is none. */
    public function size(string $path): ?int;
}
