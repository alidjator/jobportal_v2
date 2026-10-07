<?php

namespace Src\LegacyImport\Application;

/** Reads a file the legacy application stored, by the path kept in its database. Never reads outside the legacy project folder. */
interface LegacyFiles
{
    /** Whether the legacy project folder is configured at all. */
    public function isConfigured(): bool;

    /**
     * The file's bytes, or null when there is no such file, the path is not a plain relative path inside the folder, or the file is too large to read.
     */
    public function read(string $legacyPath): ?string;
}
