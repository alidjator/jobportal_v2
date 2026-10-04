<?php

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Src\Shared\Application\FileStorage;

/** Uses the application's default filesystem disk, which is private (`local`, switchable to S3-compatible storage by configuration). */
final readonly class LaravelFileStorage implements FileStorage
{
    public function __construct(private Factory $filesystems) {}

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk();
    }
}
