<?php

namespace Src\Assessment\Application\Register;

/** The register as text cells for a CSV file: the name of the file, the header row and the lines (read as they are written). */
final readonly class RegisterExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public function __construct(
        public string $filename,
        public array $headers,
        public iterable $rows,
    ) {}
}
