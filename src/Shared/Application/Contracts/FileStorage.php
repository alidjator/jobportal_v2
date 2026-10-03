<?php

namespace Src\Shared\Application\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Penyimpanan file upload di `public/` (kompatibel dengan helper lama
 * `uploadFileToPublic`, `uploadImage`, `deleteFile`). Path yang dikembalikan
 * relatif terhadap `public/` dan langsung disimpan di kolom DB.
 */
interface FileStorage
{
    /** Simpan file apa adanya ke `uploads/{directory}/{hashName}`. */
    public function storeFile(UploadedFile $file, string $directory): string;

    /**
     * Simpan gambar terkompresi ke `{directory}/{time_uniqid.ext}`.
     *
     * @param  array{0:int,1:int}|null  $fit  [lebar, tinggi] untuk crop/resize
     */
    public function storeImage(UploadedFile $file, string $directory, ?array $fit = null, int $quality = 60): string;

    /** Hapus file; aman terhadap null, path di luar `public/`, dan gambar default. */
    public function delete(?string $path): void;
}
