<?php

namespace Src\JobSeeker\Application\Export;

use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Privacy\PersonalDataExporters;
use ZipArchive;

/**
 * Makes the ZIP archive of one person's data: `data.json` (a `version`, the time, and one key per exporter), `files/<key>/...` and `README.txt`. A stored file
 * that is gone is skipped and reported in the log; it never stops the export. The archive is built in a temporary file and returned as bytes.
 */
final readonly class BuildDataExportArchive
{
    public const int VERSION = 1;

    public function __construct(private PersonalDataExporters $exporters, private FileStorage $files, private LoggerInterface $logger) {}

    public function build(int $userId, DateTimeImmutable $at): string
    {
        $generatedAt = $at->format(DateTimeInterface::ATOM);
        $data = ['version' => self::VERSION, 'generated_at' => $generatedAt];
        $archiveFiles = [];

        foreach ($this->exporters->all() as $exporter) {
            $key = $exporter->key();

            if (isset($data[$key])) {
                throw new LogicException("Two personal data exporters use the key {$key}.");
            }

            $section = $exporter->export($userId);
            $data[$key] = $section->data;

            foreach ($section->files as $file) {
                $archiveFiles['files/'.$key.'/'.self::safeName($file->archiveName)] = $file->storagePath;
            }
        }

        $temporary = tempnam(sys_get_temp_dir(), 'export');

        if ($temporary === false) {
            throw new RuntimeException('No temporary file for the archive.');
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The archive could not be opened.');
            }

            $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $zip->addFromString('README.txt', DataExportReadme::text(array_values(array_diff(array_keys($data), ['version', 'generated_at'])), $generatedAt));

            foreach ($archiveFiles as $name => $path) {
                $contents = $this->files->get($path);

                if ($contents === null) {
                    $this->logger->warning('A file of a personal data export is missing and was left out.', ['user_id' => $userId, 'archive_name' => $name]);

                    continue;
                }

                $zip->addFromString($name, $contents);
            }

            if (! $zip->close()) {
                throw new RuntimeException('The archive could not be written.');
            }

            $bytes = file_get_contents($temporary);

            if ($bytes === false) {
                throw new RuntimeException('The archive could not be read back.');
            }

            return $bytes;
        } finally {
            @unlink($temporary);
        }
    }

    /** Each segment of the name keeps letters, digits, dots, dashes and underscores; a segment made only of dots goes. */
    private static function safeName(string $name): string
    {
        $segments = [];

        foreach (explode('/', $name) as $segment) {
            $clean = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $segment));

            if ($clean !== '' && trim($clean, '.') !== '') {
                $segments[] = $clean;
            }
        }

        return $segments === [] ? 'file' : implode('/', $segments);
    }
}
