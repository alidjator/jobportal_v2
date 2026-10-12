<?php

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Factory;
use RuntimeException;
use Src\Shared\Application\FileStreams;

/** The streaming side of the application's private disk (see {@see LaravelFileStorage}). */
final readonly class LaravelFileStreams implements FileStreams
{
    public function __construct(private Factory $filesystems) {}

    public function putStream(string $path, $stream): void
    {
        if ($this->filesystems->disk()->put($path, $stream) === false) {
            throw new RuntimeException("The file {$path} could not be written.");
        }
    }

    public function readStream(string $path)
    {
        $disk = $this->filesystems->disk();

        return $disk->exists($path) ? ($disk->readStream($path) ?: null) : null;
    }

    public function size(string $path): ?int
    {
        $disk = $this->filesystems->disk();

        return $disk->exists($path) ? $disk->size($path) : null;
    }
}
