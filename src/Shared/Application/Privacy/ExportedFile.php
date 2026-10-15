<?php

namespace Src\Shared\Application\Privacy;

/** A stored file that goes into the export: where it is on the private disk, and the name it gets inside the archive (below `files/`, unique among all exporters). */
final readonly class ExportedFile
{
    public function __construct(public string $storagePath, public string $archiveName) {}
}
