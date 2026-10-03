<?php

namespace Tests\Feature\Shared;

use Illuminate\Http\UploadedFile;
use Src\Shared\Application\Contracts\FileStorage;
use Tests\TestCase;

class PublicFileStorageTest extends TestCase
{
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink(public_path($path));
        }
        parent::tearDown();
    }

    public function test_store_file_and_delete(): void
    {
        $storage = app(FileStorage::class);
        $path = $storage->storeFile(UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'), 'file/_test');
        $this->created[] = $path;

        $this->assertStringStartsWith('uploads/file/_test/', $path);
        $this->assertFileExists(public_path($path));

        $storage->delete($path);
        $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_store_image_with_fit(): void
    {
        $path = app(FileStorage::class)->storeImage(UploadedFile::fake()->image('a.jpg', 400, 300), 'images/_test', [100, 100]);
        $this->created[] = $path;

        [$w, $h] = getimagesize(public_path($path));
        $this->assertSame([100, 100], [$w, $h]);
    }

    public function test_delete_ignores_unsafe_paths(): void
    {
        $storage = app(FileStorage::class);
        $storage->delete(null);
        $storage->delete('backend/image/default.png');
        $storage->delete('../.env');

        $this->assertFileExists(base_path('.env'));
    }
}
