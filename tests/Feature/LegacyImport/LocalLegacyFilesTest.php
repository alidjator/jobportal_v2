<?php

namespace Tests\Feature\LegacyImport;

use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Infrastructure\LocalLegacyFiles;

final class LocalLegacyFilesTest extends TestCase
{
    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/legacy-files-'.bin2hex(random_bytes(4));
        $this->root = $base.'/project';
        $this->outside = $base.'/outside';

        foreach (['/public/uploads', '/storage/app/resumes', '/storage/app/public/docs', '/..'] as $directory) {
            @mkdir($this->root.$directory, 0777, true);
        }

        @mkdir($this->outside, 0777, true);
        file_put_contents($this->root.'/public/uploads/photo.png', 'PUBLIC');
        file_put_contents($this->root.'/storage/app/resumes/cv.pdf', 'STORAGE');
        file_put_contents($this->root.'/storage/app/public/docs/doc.pdf', 'STORAGEPUBLIC');
        file_put_contents($this->outside.'/secret.txt', 'SECRET');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg(dirname($this->root)));

        parent::tearDown();
    }

    private function files(int $max = 1024): LocalLegacyFiles
    {
        return new LocalLegacyFiles($this->root, $max);
    }

    public function test_a_file_is_found_under_public_storage_app_or_storage_app_public(): void
    {
        $this->assertSame('PUBLIC', $this->files()->read('uploads/photo.png'));
        $this->assertSame('STORAGE', $this->files()->read('resumes/cv.pdf'));
        $this->assertSame('STORAGEPUBLIC', $this->files()->read('docs/doc.pdf'));
        $this->assertNull($this->files()->read('uploads/missing.png'));
        $this->assertTrue($this->files()->isConfigured());
    }

    public function test_a_path_that_could_leave_the_folder_is_refused(): void
    {
        foreach (['../outside/secret.txt', 'uploads/../../../outside/secret.txt', '/etc/passwd', $this->outside.'/secret.txt', "uploads/photo.png\0.txt", '', '  '] as $path) {
            $this->assertNull($this->files()->read($path), $path);
        }
    }

    public function test_a_link_that_points_outside_the_folder_is_refused(): void
    {
        symlink($this->outside.'/secret.txt', $this->root.'/public/uploads/link.txt');

        $this->assertNull($this->files()->read('uploads/link.txt'));
    }

    public function test_a_file_larger_than_the_limit_is_not_read(): void
    {
        $this->assertSame('PUBLIC', $this->files(6)->read('uploads/photo.png'));
        $this->assertNull($this->files(5)->read('uploads/photo.png'));
    }

    public function test_an_empty_or_missing_folder_means_not_configured(): void
    {
        $this->assertFalse((new LocalLegacyFiles('', 1024))->isConfigured());
        $this->assertFalse((new LocalLegacyFiles('/no/such/folder', 1024))->isConfigured());
        $this->assertNull((new LocalLegacyFiles('', 1024))->read('uploads/photo.png'));
    }
}
