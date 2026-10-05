<?php

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;
use Src\Shared\Application\FileStorage;

/** Uses the application's default filesystem disk, which is private (`local`, switchable to S3-compatible storage by configuration). */
final readonly class LaravelFileStorage implements FileStorage
{
    public function __construct(private Factory $filesystems) {}

    public function put(string $path, string $contents): void
    {
        // `put` returns false when the disk could not write; a file that was not stored must never look stored.
        if ($this->disk()->put($path, $contents) === false) {
            throw new RuntimeException("The file {$path} could not be written.");
        }
    }

    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk();
    }
}
