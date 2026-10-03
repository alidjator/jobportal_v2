<?php

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Src\Shared\Application\Contracts\FileStorage;

class PublicFileStorage implements FileStorage
{
    private const DEFAULT_IMAGE = 'backend/image/default.png';

    public function storeFile(UploadedFile $file, string $directory): string
    {
        $directory = 'uploads/'.trim($directory, '/');
        $file->move(public_path($directory), $file->hashName());

        return $directory.'/'.$file->hashName();
    }

    public function storeImage(UploadedFile $file, string $directory, ?array $fit = null, int $quality = 60): string
    {
        $directory = trim($directory, '/');
        $name = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

        if (! is_dir(public_path($directory))) {
            mkdir(public_path($directory), 0775, true);
        }

        $image = ImageManager::usingDriver(Driver::class)->decodePath($file->getRealPath());

        if ($fit) {
            $image = $image->cover($fit[0], $fit[1]);
        }

        $image->save(public_path($directory.'/'.$name), quality: $quality);

        return $directory.'/'.$name;
    }

    public function delete(?string $path): void
    {
        if (! $path || $path === self::DEFAULT_IMAGE || str_contains($path, '..')) {
            return;
        }

        $full = public_path($path);

        if (is_file($full)) {
            @unlink($full);
        }
    }
}
