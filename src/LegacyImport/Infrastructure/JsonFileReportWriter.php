<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Contracts\Filesystem\Filesystem;
use Src\LegacyImport\Application\ReportWriter;

/** Writes the report as JSON under `legacy-import/` on the private local disk (never publicly reachable). */
final readonly class JsonFileReportWriter implements ReportWriter
{
    private const string DIRECTORY = 'legacy-import';

    public function __construct(private Filesystem $disk) {}

    public function write(array $report, string $runName): string
    {
        $path = self::DIRECTORY.'/'.$runName.'.json';

        $this->disk->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $path;
    }
}
