<?php

namespace Tests\Feature\CandidateProfile;

use Illuminate\Http\UploadedFile;
use Src\Shared\Application\Contracts\FileStorage;

/** Fake FileStorage: tidak menyentuh disk, hanya mencatat panggilan. */
class FakeFileStorage implements FileStorage
{
    /** @var list<string> */
    public array $stored = [];

    /** @var list<string> */
    public array $deleted = [];

    public function storeFile(UploadedFile $file, string $directory): string
    {
        return $this->stored[] = 'uploads/'.$directory.'/'.uniqid('fake_').'.pdf';
    }

    public function storeImage(UploadedFile $file, string $directory, ?array $fit = null, int $quality = 60): string
    {
        return $this->stored[] = $directory.'/'.uniqid('fake_').'.jpg';
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $this->deleted[] = $path;
        }
    }
}
