<?php

namespace Src\LegacyImport\Infrastructure;

use Src\LegacyImport\Application\LegacyFiles;

/**
 * The legacy project folder on this server. A stored path is looked up under `public`, `storage/app` and `storage/app/public` (the places the legacy
 * application saved uploads). A path with `..`, a null byte or a leading slash is refused, and the file found must really be inside the folder (links are
 * resolved), so a stored value can never lead outside it.
 */
final readonly class LocalLegacyFiles implements LegacyFiles
{
    private const array BASES = ['public', 'storage/app', 'storage/app/public'];

    public function __construct(private string $root, private int $maxBytes) {}

    public function isConfigured(): bool
    {
        return $this->root !== '' && is_dir($this->root);
    }

    public function read(string $legacyPath): ?string
    {
        $relative = trim(str_replace('\\', '/', $legacyPath));

        if (! $this->isConfigured() || $relative === '' || str_contains($relative, "\0") || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
            return null;
        }

        $root = realpath($this->root);

        if ($root === false) {
            return null;
        }

        foreach (self::BASES as $base) {
            $real = realpath($root.'/'.$base.'/'.$relative);

            if ($real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_file($real)) {
                continue;
            }

            $size = filesize($real);

            if ($size === false || $size > $this->maxBytes) {
                return null;
            }

            $contents = file_get_contents($real);

            return $contents === false ? null : $contents;
        }

        return null;
    }
}
